<?php
namespace Accessibility_Auditor;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for AI-powered accessibility fix routes.
 *
 * This class is intentionally thin: it handles routing, auth, and input
 * validation only. All business logic lives in dedicated helper classes:
 *
 *   ClaudeClient           — Anthropic API HTTP calls
 *   AiResponseNormalizer   — Claude output cleanup / JSON extraction
 *   BricksElementFinder    — Bricks element tree traversal + issue-to-element mapping
 *   BricksPatchApplier     — Deep-merge / deep-remove patch application
 *   BricksPatchValidator   — Patch safety whitelist (element_id, settings shape, _cssCustom)
 *   Revisions              — Bricks snapshots, changelog, audit history
 */
class AI {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aa/v1', '/guided-fix', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'generate_guided_fix' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );

        register_rest_route( 'aa/v1', '/auto-fix', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'apply_auto_fix' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );

        register_rest_route( 'aa/v1', '/save-fix', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_fix' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );

        register_rest_route( 'aa/v1', '/revert-fix', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'revert_fix' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );
    }

    /** Baseline gate: must be at least an editor. Per-resource checks happen inside each callback. */
    public function check_permissions() {
        return current_user_can( 'edit_posts' );
    }

    // =========================================================================
    // Route: /guided-fix
    // =========================================================================

    public function generate_guided_fix( WP_REST_Request $request ) {
        $params  = $request->get_json_params();
        $context = $params['context'] ?? $params;

        $response = ClaudeClient::request( $this->build_guided_prompt( $context ) );

        if ( is_wp_error( $response ) ) {
            return rest_ensure_response( [ 'error' => true, 'message' => $response->get_error_message() ] );
        }

        return rest_ensure_response( [ 'steps' => $response ] );
    }

    // =========================================================================
    // Route: /auto-fix
    // =========================================================================

    public function apply_auto_fix( WP_REST_Request $request ) {
        $payload = $request->get_json_params();

        // 1. Input validation.
        $issue = $payload['issue'] ?? null;
        if ( ! $issue ) {
            return new WP_Error( 'missing_issue', 'Missing issue data.' );
        }

        $post_id = intval( $payload['post_id'] ?? 0 );
        if ( ! $post_id ) {
            return new WP_Error( 'missing_post', 'post_id is required.', [ 'status' => 400 ] );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to edit this post.', [ 'status' => 403 ] );
        }

        // 2. Issue whitelist — only auto-fix supported axe rule IDs.
        $supported_rules = [
            'color-contrast',    // CSS color fix via _cssCustom
            'image-alt',         // settings.altText
            'link-name',         // settings.url.ariaLabel / aria-label attribute
            'button-name',       // aria-label attribute
            'frame-title',       // title / aria-label attribute
            'input-image-alt',   // settings.altText on input[type=image]
            'aria-label',        // generic aria-label fixes
            'aria-labelledby',   // aria-labelledby attribute
            'aria-hidden-focus', // remove aria-hidden from focusable elements
        ];
        $rule_id = $issue['id'] ?? '';
        if ( ! in_array( $rule_id, $supported_rules, true ) ) {
            // Unsupported rule — fall back to guided instructions instead of an error.
            $steps = ClaudeClient::request( $this->build_guided_prompt( $issue ) );
            if ( is_wp_error( $steps ) ) {
                $steps = '<p>Auto-fix is not supported for this issue type. Please review and fix manually.</p>';
            }
            return rest_ensure_response( [ 'guided_fallback' => true, 'steps' => $steps ] );
        }

        error_log( sprintf( '[AA:auto-fix] START post_id=%d issue_id=%s nodes=%d', $post_id, $issue['id'] ?? '?', count( $issue['nodes'] ?? [] ) ) );

        // 3. Load Bricks content.
        if ( ! defined( 'BRICKS_DB_PAGE_CONTENT' ) ) {
            return new WP_Error( 'bricks_unavailable', 'Bricks Builder is not active on this installation.', [ 'status' => 503 ] );
        }

        $content  = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
        $elements = is_array( $content ) ? $content : json_decode( $content, true );

        if ( empty( $elements ) || ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_content', 'Invalid or empty Bricks content.' );
        }

        // 4. Map issue to affected Bricks elements.
        $target_elements = BricksElementFinder::from_issue( $elements, $issue );
        error_log( sprintf( '[AA:auto-fix] elements=%d targets=%d', count( $elements ), count( $target_elements ) ) );

        if ( empty( $target_elements ) ) {
            error_log( '[AA:auto-fix] No matching Bricks elements found — returning error' );
            return new WP_Error( 'missing_elements', 'No matching Bricks elements found.' );
        }

        // 5. Snapshot before mutating (supports rollback).
        Revisions::save_bricks_snapshot( $post_id, $elements, 'pre_fix_backup' );

        $applied = [];

        // 6. Per-element: build prompt → call Claude → validate → apply patch.
        foreach ( $target_elements as $element ) {

            // Extract color data from axe checks (color-contrast issues).
            $color_context = '';
            foreach ( $issue['nodes'] ?? [] as $node ) {
                foreach ( array_merge( $node['any'] ?? [], $node['all'] ?? [] ) as $check ) {
                    if ( ! empty( $check['data']['fgColor'] ) ) {
                        $color_context = sprintf(
                            "\n\n🎨 Computed color data:\n- Foreground: %s\n- Background: %s\n- Ratio: %s (required: %s)\n- Font: %s / weight: %s",
                            $check['data']['fgColor'],
                            $check['data']['bgColor'] ?? 'unknown',
                            $check['data']['contrastRatio'] ?? 'unknown',
                            $check['data']['expectedContrastRatio'] ?? '4.5:1',
                            $check['data']['fontSize'] ?? 'unknown',
                            $check['data']['fontWeight'] ?? 'unknown'
                        );
                        break 2;
                    }
                }
            }

            $prompt = sprintf(
                'You are an AI accessibility assistant for WordPress using the Bricks Builder framework and AutomaticCSS.

You are provided with:
1️⃣ An accessibility issue (axe-core JSON).
2️⃣ The Bricks element JSON responsible for that issue.
3️⃣ The element type name: **%s**%s

Your task:
- Analyze the accessibility issue and generate the *minimal JSON patch* to fix it.
- If any attribute or key should be removed (e.g., invalid aria, redundant role), include it under `removed_keys` using nested JSON.
- If new attributes or keys are required, include them under `added_keys`.
- If existing attributes should be updated, include them under `changes`.
- Do NOT return the full element — only the patch object.

⚙️ Output must be the minimal patch object in this exact format:
{
  "element_id": "<same ID as provided>",
  "changes":      { "settings": { ... } },
  "added_keys":   { "settings": { ... } },
  "removed_keys": { "settings": { ... } }
}

📘 Accessibility guidance:
- Links/buttons → add meaningful aria-labels, remove duplicate or empty attributes.
- Images → for alt text use settings.altText ONLY. Example: {"added_keys": {"settings": {"altText": "Descriptive text"}}}. Never use settings.image.alt.
- Iframes/videos → add a title or aria-label; remove redundant attributes.
- Text/headings → fix tag hierarchy (settings.tag), remove unnecessary roles.
- Color contrast → use `settings._cssCustom` with the LITERAL element selector (NOT %%root%%).
  The element ID is in the Bricks Element JSON as "id". Prefix it with "#brxe-".
  Example for element id "abc123": {"added_keys": {"settings": {"_cssCustom": "#brxe-abc123 { color: #1a1a1a; }"}}}
  Target at least 5:1 contrast ratio. If _cssCustom already exists, use "changes" not "added_keys".

⚙️ Output Rules:
- Must be valid JSON (no markdown, comments, or explanations).
- Use only nested JSON objects (no dot-notation paths).
- Must include only changed, added, or removed keys.
- Always include the `element_id` copied exactly from the provided element JSON.

=== Accessibility Issue JSON ===
%s

=== Bricks Element JSON ===
%s

Output only the JSON patch.',
                strtoupper( $element['name'] ?? 'UNKNOWN' ),
                $color_context,
                json_encode( $issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
                json_encode( $element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
            );

            try {
                error_log( sprintf( '[AA:auto-fix] Calling Claude for element_id=%s type=%s', $element['id'] ?? '?', $element['name'] ?? '?' ) );
                $response = ClaudeClient::request( $prompt, true );
                $json     = AiResponseNormalizer::normalize( $response );
                error_log( '[AA:auto-fix] Claude response (first 300): ' . substr( $json, 0, 300 ) );

                try {
                    $patch = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

                    $id = $patch['element_id'] ?? null;
                    if ( ! $id ) {
                        error_log( '[AAI] Skipping patch: no element_id in response' );
                        continue;
                    }

                    $validation_error = BricksPatchValidator::validate( $patch, $element['id'] );
                    if ( $validation_error !== null ) {
                        error_log( "[AA:auto-fix] Patch rejected: {$validation_error}" );
                        continue;
                    }

                    $original = BricksElementFinder::find( $elements, $id );
                    if ( ! $original ) {
                        error_log( "[AAI] Element not found in tree: {$id}" );
                        continue;
                    }

                    $updated = BricksPatchApplier::apply( $original, $patch );

                    if ( BricksElementFinder::update( $elements, $id, $updated ) ) {
                        $applied[] = $updated;
                    } else {
                        error_log( "[AAI] Failed to update element {$id} in tree" );
                    }

                } catch ( \JsonException $e ) {
                    error_log( '[AAI] JSON decode failed: ' . $e->getMessage() );
                    error_log( 'Bad JSON: ' . substr( $json, 0, 300 ) );
                    return new WP_REST_Response( [
                        'error'   => true,
                        'message' => 'AI returned invalid JSON.',
                        'details' => $e->getMessage(),
                        'raw'     => substr( $json, 0, 300 ),
                    ], 500 );
                }

            } catch ( \Throwable $e ) {
                error_log( '[AAI] Unexpected error during AI fix: ' . $e->getMessage() );
                continue;
            }
        }

        // 7. Persist, flush caches, return.
        try {
            update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $elements );

            if ( function_exists( 'bricks_flush_post_css' ) ) {
                bricks_flush_post_css( $post_id );
            }
            if ( function_exists( 'bricks_clear_rendered_data' ) ) {
                bricks_clear_rendered_data( $post_id );
            }

            do_action( 'bricks_after_save_post', $post_id );

            $revision_key = Revisions::save_bricks_snapshot( $post_id, $elements, 'ai_fix' );
            $changelog    = Revisions::generate_changelog( $applied );

            error_log( sprintf( '[AA:auto-fix] DONE applied=%d revision_key=%s', count( $applied ), $revision_key ) );

            return new WP_REST_Response( [
                'success'   => true,
                'message'   => 'Accessibility fixes applied successfully.',
                'changes'   => $applied,
                'revision'  => $revision_key,
                'changelog' => $changelog,
            ] );

        } catch ( \Throwable $e ) {
            error_log( '[AAI] Failed to save Bricks content: ' . $e->getMessage() );
            return new WP_REST_Response( [
                'error'   => true,
                'message' => 'Failed to save updated Bricks content.',
                'details' => $e->getMessage(),
            ], 500 );
        }
    }

    // =========================================================================
    // Route: /save-fix  (accept — log to audit trail)
    // =========================================================================

    public function save_fix( WP_REST_Request $request ) {
        $payload  = $request->get_json_params();
        $post_id  = intval( $payload['post_id'] ?? 0 );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'forbidden', 'Unauthorized.', [ 'status' => 403 ] );
        }

        $revision_key = sanitize_text_field( $payload['revision_key'] ?? '' );

        Revisions::log_autofix( $post_id, [
            'revision_key' => $revision_key,
            'action'       => 'accepted',
        ] );

        return rest_ensure_response( [ 'success' => true ] );
    }

    // =========================================================================
    // Route: /revert-fix  (reject — restore from snapshot)
    // =========================================================================

    public function revert_fix( WP_REST_Request $request ) {
        $payload      = $request->get_json_params();
        $post_id      = intval( $payload['post_id'] ?? 0 );
        $revision_key = sanitize_text_field( $payload['revision_key'] ?? '' );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'forbidden', 'Unauthorized.', [ 'status' => 403 ] );
        }
        if ( ! $revision_key ) {
            return new WP_Error( 'missing_revision', 'revision_key is required.', [ 'status' => 400 ] );
        }

        $revision_json = get_post_meta( $post_id, $revision_key, true );
        if ( ! $revision_json ) {
            return new WP_Error( 'revision_not_found', 'Revision not found.', [ 'status' => 404 ] );
        }

        $revision = json_decode( $revision_json, true );
        $elements = $revision['elements'] ?? null;

        if ( empty( $elements ) || ! is_array( $elements ) ) {
            return new WP_Error( 'invalid_revision', 'Revision data is invalid.', [ 'status' => 500 ] );
        }

        update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $elements );

        if ( function_exists( 'bricks_flush_post_css' ) ) {
            bricks_flush_post_css( $post_id );
        }
        if ( function_exists( 'bricks_clear_rendered_data' ) ) {
            bricks_clear_rendered_data( $post_id );
        }

        delete_post_meta( $post_id, $revision_key );

        return rest_ensure_response( [ 'success' => true, 'message' => 'Fix reverted successfully.' ] );
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function build_guided_prompt( array $context ): string {
        return "You are an accessibility assistant. The site uses Bricks Builder and AutomaticCSS.\n"
             . "Provide step-by-step WCAG fix instructions in this exact HTML format:\n\n"
             . "<div class=\"aa-resolution-steps-list\">\n"
             . "  <ol style=\"margin:0; padding-left:18px;\">\n"
             . "    <li>[Step 1 with optional <ul> for sub-steps]</li>\n"
             . "    <li>[Step 2 ...]</li>\n"
             . "    <li>[Verification / testing]</li>\n"
             . "  </ol>\n"
             . "</div>\n\n"
             . "Guidelines:\n"
             . "- Always return valid HTML only (no markdown, no headings like ##).\n"
             . "- Use <ol> for main steps.\n"
             . "- Use <ul> for sub-steps.\n"
             . "- Keep Bricks Builder terminology (Navigator, Sidebar, Edit with Bricks, etc.).\n"
             . "- Return ONLY the HTML block.\n\n"
             . "Accessibility issue to fix:\n"
             . json_encode( $context, JSON_PRETTY_PRINT );
    }
}
