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
        $trace_id = sanitize_text_field( (string) ( $params['trace_id'] ?? '' ) );
        $issue_id = $context['issueId'] ?? ( $context['id'] ?? null );
        $prompt = $this->build_guided_prompt( $context );

        Loader::aa_log( 'guided_fix.start', [
            'trace_id' => $trace_id,
            'issue_id' => $issue_id,
        ] );

        $response = ClaudeClient::request_with_meta( $prompt, false, [
            'trace_id'       => $trace_id,
            'call_mode'      => 'guided_fix',
            'post_id'        => absint( $context['post_id'] ?? $params['post_id'] ?? 0 ),
            'rule_id'        => sanitize_key( (string) $issue_id ),
            'prompt_version' => 'guided_v1',
        ] );

        if ( is_wp_error( $response ) ) {
            Loader::aa_log( 'guided_fix.error', [ 'trace_id' => $trace_id, 'message' => $response->get_error_message() ] );
            return rest_ensure_response( [ 'error' => true, 'message' => $response->get_error_message(), 'trace_id' => $trace_id ] );
        }

        Loader::aa_log( 'guided_fix.success', [
            'trace_id'      => $trace_id,
            'input_tokens'  => $response['input_tokens'] ?? 0,
            'output_tokens' => $response['output_tokens'] ?? 0,
            'latency_ms'    => $response['latency_ms'] ?? 0,
        ] );
        return rest_ensure_response( [ 'steps' => $response['content'] ?? '', 'trace_id' => $trace_id ] );
    }

    // =========================================================================
    // Route: /auto-fix
    // =========================================================================

    public function apply_auto_fix( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $trace_id = sanitize_text_field( (string) ( $payload['trace_id'] ?? '' ) );

        // 1. Input validation.
        $issue = $payload['issue'] ?? null;
        if ( ! $issue ) {
            Loader::aa_log( 'auto_fix.missing_issue', [ 'trace_id' => $trace_id ] );
            return new WP_Error( 'missing_issue', 'Missing issue data.' );
        }

        $post_id = intval( $payload['post_id'] ?? 0 );
        if ( ! $post_id ) {
            Loader::aa_log( 'auto_fix.missing_post', [ 'trace_id' => $trace_id ] );
            return new WP_Error( 'missing_post', 'post_id is required.', [ 'status' => 400 ] );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            Loader::aa_log( 'auto_fix.forbidden', [ 'trace_id' => $trace_id, 'post_id' => $post_id ] );
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
            Loader::aa_log( 'auto_fix.guided_fallback', [ 'trace_id' => $trace_id, 'post_id' => $post_id, 'rule_id' => $rule_id ] );
            // Log so unsupported rules can be identified and promoted to the whitelist later.
            error_log( sprintf(
                '[AA:auto-fix] UNSUPPORTED_RULE rule_id="%s" impact="%s" nodes=%d description="%s" — falling back to guided fix',
                $rule_id,
                $issue['impact'] ?? 'unknown',
                count( $issue['nodes'] ?? [] ),
                substr( $issue['description'] ?? '', 0, 120 )
            ) );

            // Unsupported rule — fall back to guided instructions instead of an error.
            return $this->guided_fallback_response(
                $issue,
                $trace_id,
                'Auto-fix is not supported for this issue type. Please review and fix manually.'
            );
        }

        Loader::aa_log( 'auto_fix.start', [ 'trace_id' => $trace_id, 'post_id' => $post_id, 'rule_id' => $rule_id, 'nodes' => count( $issue['nodes'] ?? [] ) ] );
        error_log( sprintf( '[AA:auto-fix] START post_id=%d issue_id=%s nodes=%d', $post_id, $issue['id'] ?? '?', count( $issue['nodes'] ?? [] ) ) );

        // 3. Load Bricks content. Bricks constant may be unavailable (or point to a different key)
        // in some contexts, so fall back to the common `bricks_data` meta key used by fixtures.
        $content_key_candidates = [];
        if ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && is_string( BRICKS_DB_PAGE_CONTENT ) && BRICKS_DB_PAGE_CONTENT !== '' ) {
            $content_key_candidates[] = BRICKS_DB_PAGE_CONTENT;
        }
        $content_key_candidates[] = 'bricks_data';
        $content_key_candidates = array_values( array_unique( $content_key_candidates ) );

        $content          = null;
        $elements         = null;
        $content_meta_key = null;
        foreach ( $content_key_candidates as $candidate_key ) {
            $candidate_content  = get_post_meta( $post_id, $candidate_key, true );
            $candidate_elements = is_array( $candidate_content ) ? $candidate_content : json_decode( $candidate_content, true );
            if ( ! empty( $candidate_elements ) && is_array( $candidate_elements ) ) {
                $content          = $candidate_content;
                $elements         = $candidate_elements;
                $content_meta_key = $candidate_key;
                break;
            }
        }

        if ( empty( $elements ) || ! is_array( $elements ) ) {
            Loader::aa_log( 'auto_fix.invalid_content', [
                'trace_id'    => $trace_id,
                'post_id'     => $post_id,
                'tried_keys'  => $content_key_candidates,
                'bricks_const' => defined( 'BRICKS_DB_PAGE_CONTENT' ) ? BRICKS_DB_PAGE_CONTENT : 'UNDEF',
            ] );
            return new WP_Error( 'invalid_content', 'Invalid or empty Bricks content.' );
        }
        Loader::aa_log( 'auto_fix.content_loaded', [ 'trace_id' => $trace_id, 'post_id' => $post_id, 'meta_key' => $content_meta_key ] );

        // 4. Map issue to affected Bricks elements.
        $target_elements = BricksElementFinder::from_issue( $elements, $issue );
        error_log( sprintf( '[AA:auto-fix] elements=%d targets=%d', count( $elements ), count( $target_elements ) ) );

        if ( empty( $target_elements ) ) {
            Loader::aa_log( 'auto_fix.missing_elements', [
                'trace_id' => $trace_id,
                'post_id'  => $post_id,
                'rule_id'  => $rule_id,
            ] );
            error_log( '[AA:auto-fix] No matching Bricks elements found — falling back to guided fix' );
            return $this->guided_fallback_response(
                $issue,
                $trace_id,
                'Auto-fix could not map this issue to editable page-level Bricks elements. It may belong to a template, global element, or rendered wrapper.'
            );
        }

        // 5. Snapshot before mutating (supports rollback).
        Revisions::save_bricks_snapshot( $post_id, $elements, 'pre_fix_backup' );

        $applied = [];

        // 6. Per-element: build prompt → call Claude → validate → apply patch.
        foreach ( $target_elements as $element ) {
            $prompt_package = $this->build_auto_fix_prompt_package( $issue, $element );
            $prompt = $prompt_package['prompt'];
            $prompt_version = $prompt_package['prompt_version'];
            $max_tokens = $prompt_package['max_tokens'];
            $system_prompt = $prompt_package['system_prompt'];

            try {
                error_log( sprintf( '[AA:auto-fix] Calling Claude for element_id=%s type=%s', $element['id'] ?? '?', $element['name'] ?? '?' ) );
                $response = ClaudeClient::request_with_meta( $prompt, true, [
                    'trace_id'       => $trace_id,
                    'call_mode'      => 'auto_fix',
                    'post_id'        => $post_id,
                    'rule_id'        => sanitize_key( (string) $rule_id ),
                    'component'      => sanitize_key( (string) ( $element['name'] ?? '' ) ),
                    'prompt_version' => $prompt_version,
                    'max_tokens'     => $max_tokens,
                    'system_prompt'  => $system_prompt,
                ] );
                $json     = AiResponseNormalizer::normalize( $response );
                error_log( '[AA:auto-fix] Claude response (first 300): ' . substr( $json, 0, 300 ) );

                try {
                    $patch = $this->decode_ai_patch_json( $json );

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
            update_post_meta( $post_id, $content_meta_key ?: 'bricks_data', $elements );

            if ( function_exists( 'bricks_flush_post_css' ) ) {
                bricks_flush_post_css( $post_id );
            }
            if ( function_exists( 'bricks_clear_rendered_data' ) ) {
                bricks_clear_rendered_data( $post_id );
            }

            do_action( 'bricks_after_save_post', $post_id );

            $revision_key = Revisions::save_bricks_snapshot( $post_id, $elements, 'ai_fix' );
            $changelog    = Revisions::generate_changelog( $applied );

            Loader::aa_log( 'auto_fix.success', [
                'trace_id'     => $trace_id,
                'post_id'      => $post_id,
                'rule_id'      => $rule_id,
                'applied_count'=> count( $applied ),
                'revision'     => $revision_key,
            ] );
            error_log( sprintf( '[AA:auto-fix] DONE applied=%d revision_key=%s', count( $applied ), $revision_key ) );

            return new WP_REST_Response( [
                'success'   => true,
                'message'   => 'Accessibility fixes applied successfully.',
                'changes'   => $applied,
                'revision'  => $revision_key,
                'revision_key' => $revision_key,
                'changelog' => $changelog,
                'trace_id'  => $trace_id,
            ] );

        } catch ( \Throwable $e ) {
            Loader::aa_log( 'auto_fix.save_error', [ 'trace_id' => $trace_id, 'post_id' => $post_id, 'message' => $e->getMessage() ] );
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

        $content_meta_key = ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && is_string( BRICKS_DB_PAGE_CONTENT ) && BRICKS_DB_PAGE_CONTENT !== '' )
            ? BRICKS_DB_PAGE_CONTENT
            : 'bricks_data';
        if ( empty( get_post_meta( $post_id, $content_meta_key, true ) ) && ! empty( get_post_meta( $post_id, 'bricks_data', true ) ) ) {
            $content_meta_key = 'bricks_data';
        }

        update_post_meta( $post_id, $content_meta_key, $elements );

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

    private function build_auto_fix_prompt_package( array $issue, array $element ): array {
        $rule_id = sanitize_key( (string) ( $issue['id'] ?? '' ) );

        switch ( $rule_id ) {
            case 'image-alt':
                return $this->build_image_alt_prompt_package( $issue, $element );
            case 'link-name':
                return $this->build_link_name_prompt_package( $issue, $element );
            case 'frame-title':
                return $this->build_frame_title_prompt_package( $issue, $element );
            default:
                return $this->build_generic_auto_fix_prompt_package( $issue, $element );
        }
    }

    private function build_link_name_prompt_package( array $issue, array $element ): array {
        $compact_issue = [
            'rule_id'       => $issue['id'] ?? '',
            'help'          => $issue['help'] ?? '',
            'description'   => $issue['description'] ?? '',
            'wcag_tags'     => $this->filter_wcag_tags( $issue['tags'] ?? [] ),
            'failing_node'  => $this->compact_issue_node( $issue['nodes'][0] ?? [] ),
        ];
        $element_summary = $this->compact_element_for_prompt( $element );

        $prompt = sprintf(
            "Task: return a minimal Bricks JSON patch for a page-level link-name issue.\n\nRule summary:\n%s\n\nBricks element:\n%s\n\nAllowed fix intent:\n- Update only settings.text when the empty link lives inside HTML text content.\n- Add meaningful visible link text and an aria-label.\n- Keep the existing href unchanged.\n- Do not touch unrelated markup.\n- Return only a JSON object with: element_id, changes, added_keys, removed_keys.\n\nPatch shape example:\n{\n  \"element_id\": \"%s\",\n  \"changes\": {\"settings\": {\"text\": \"...updated html...\"}},\n  \"added_keys\": {},\n  \"removed_keys\": {}\n}\n",
            wp_json_encode( $compact_issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            wp_json_encode( $element_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            (string) ( $element['id'] ?? '' )
        );

        return [
            'prompt'         => $prompt,
            'prompt_version' => 'autofix_link_name_v2',
            'max_tokens'     => 900,
            'system_prompt'  => 'Return only valid JSON for a minimal Bricks patch. No markdown. No explanations. No extra keys.',
        ];
    }

    private function build_image_alt_prompt_package( array $issue, array $element ): array {
        $compact_issue = [
            'rule_id'       => $issue['id'] ?? '',
            'help'          => $issue['help'] ?? '',
            'description'   => $issue['description'] ?? '',
            'wcag_tags'     => $this->filter_wcag_tags( $issue['tags'] ?? [] ),
            'failing_node'  => $this->compact_issue_node( $issue['nodes'][0] ?? [] ),
        ];
        $element_summary = $this->compact_element_for_prompt( $element );

        $prompt = sprintf(
            "Task: return a minimal Bricks JSON patch for a page-level image-alt issue.\n\nRule summary:\n%s\n\nBricks element:\n%s\n\nAllowed fix intent:\n- Add or update a meaningful text alternative for the image.\n- Prefer the existing HTML structure and only change the minimum required field.\n- For image components use settings.altText only.\n- For text-based embeds containing an <img>, update only settings.text with the smallest possible HTML change.\n- Do not rewrite unrelated content.\n- Return only a JSON object with: element_id, changes, added_keys, removed_keys.\n\nPatch shape example:\n{\n  \"element_id\": \"%s\",\n  \"changes\": {\"settings\": {\"altText\": \"Descriptive alt text\"}},\n  \"added_keys\": {},\n  \"removed_keys\": {}\n}\n",
            wp_json_encode( $compact_issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            wp_json_encode( $element_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            (string) ( $element['id'] ?? '' )
        );

        return [
            'prompt'         => $prompt,
            'prompt_version' => 'autofix_image_alt_v2',
            'max_tokens'     => 900,
            'system_prompt'  => 'Return only valid JSON for a minimal Bricks patch. No markdown. No explanations. No extra keys.',
        ];
    }

    private function build_frame_title_prompt_package( array $issue, array $element ): array {
        $compact_issue = [
            'rule_id'       => $issue['id'] ?? '',
            'help'          => $issue['help'] ?? '',
            'description'   => $issue['description'] ?? '',
            'wcag_tags'     => $this->filter_wcag_tags( $issue['tags'] ?? [] ),
            'failing_node'  => $this->compact_issue_node( $issue['nodes'][0] ?? [] ),
        ];
        $element_summary = $this->compact_element_for_prompt( $element );

        $prompt = sprintf(
            "Task: return a minimal Bricks JSON patch for a page-level frame-title issue.\n\nRule summary:\n%s\n\nBricks element:\n%s\n\nAllowed fix intent:\n- Add a descriptive title to the iframe.\n- If the iframe lives inside settings.text HTML, update only settings.text with the smallest possible HTML change.\n- Keep src, width, height, and existing structure unchanged.\n- Use empty objects {} for added_keys and removed_keys when nothing is added or removed.\n- Return only a JSON object with: element_id, changes, added_keys, removed_keys.\n\nPatch shape example:\n{\n  \"element_id\": \"%s\",\n  \"changes\": {\"settings\": {\"text\": \"<iframe src=\\\"https://example.com\\\" title=\\\"Descriptive title\\\"></iframe>\"}},\n  \"added_keys\": {},\n  \"removed_keys\": {}\n}\n",
            wp_json_encode( $compact_issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            wp_json_encode( $element_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            (string) ( $element['id'] ?? '' )
        );

        return [
            'prompt'         => $prompt,
            'prompt_version' => 'autofix_frame_title_v2',
            'max_tokens'     => 900,
            'system_prompt'  => 'Return only valid JSON for a minimal Bricks patch. No markdown fences. added_keys and removed_keys must be JSON objects, never arrays.',
        ];
    }

    private function build_generic_auto_fix_prompt_package( array $issue, array $element ): array {
        $compact_issue = [
            'rule_id'      => $issue['id'] ?? '',
            'impact'       => $issue['impact'] ?? '',
            'help'         => $issue['help'] ?? '',
            'description'  => $issue['description'] ?? '',
            'wcag_tags'    => $this->filter_wcag_tags( $issue['tags'] ?? [] ),
            'failing_node' => $this->compact_issue_node( $issue['nodes'][0] ?? [] ),
        ];
        $element_summary = $this->compact_element_for_prompt( $element );

        $prompt = sprintf(
            "Task: return a minimal Bricks JSON patch for this page-level accessibility issue.\n\nIssue summary:\n%s\n\nBricks element:\n%s\n\nRules:\n- Modify only what is needed to fix the issue.\n- Return only JSON with element_id, changes, added_keys, removed_keys.\n- Use nested JSON objects only.\n- Never return the full element.\n",
            wp_json_encode( $compact_issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            wp_json_encode( $element_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
        );

        return [
            'prompt'         => $prompt,
            'prompt_version' => 'autofix_v2_generic',
            'max_tokens'     => 1100,
            'system_prompt'  => 'Return only valid JSON for a minimal Bricks patch. No markdown. No explanations. No extra keys.',
        ];
    }

    private function compact_issue_node( array $node ): array {
        return [
            'target'          => $node['target'] ?? [],
            'html'            => $node['html'] ?? '',
            'failure_summary' => $node['failureSummary'] ?? '',
            'checks'          => $this->compact_checks( array_merge( $node['any'] ?? [], $node['all'] ?? [] ) ),
        ];
    }

    private function compact_checks( array $checks ): array {
        $result = [];
        foreach ( $checks as $check ) {
            $result[] = [
                'id'      => $check['id'] ?? '',
                'message' => $check['message'] ?? '',
                'data'    => $check['data'] ?? null,
            ];
        }
        return $result;
    }

    private function compact_element_for_prompt( array $element ): array {
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];
        $allowed_setting_keys = [ 'text', 'altText', 'url', 'attributes', '_cssCustom', 'tag', 'image', 'icon' ];
        $settings_subset = [];
        foreach ( $allowed_setting_keys as $key ) {
            if ( array_key_exists( $key, $settings ) ) {
                $settings_subset[ $key ] = $settings[ $key ];
            }
        }

        return [
            'id'       => $element['id'] ?? '',
            'name'     => $element['name'] ?? '',
            'parent'   => $element['parent'] ?? '',
            'settings' => $settings_subset,
        ];
    }

    private function filter_wcag_tags( array $tags ): array {
        return array_values( array_filter( $tags, static function ( $tag ) {
            return is_string( $tag ) && ( strpos( $tag, 'wcag' ) === 0 || strpos( $tag, 'EN-' ) === 0 );
        } ) );
    }

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

    private function decode_ai_patch_json( string $json ): array {
        try {
            $decoded = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
            if ( is_array( $decoded ) ) {
                return $this->normalize_patch_shape( $decoded );
            }
        } catch ( \JsonException $e ) {
            $salvaged = trim( preg_replace( '/^```(?:json)?\s*/i', '', $json ) ?? $json );
            $salvaged = trim( preg_replace( '/\s*```$/', '', $salvaged ) ?? $salvaged );
            $salvaged = preg_replace( '/"added_keys"\s*:\s*\[\s*\]/', '"added_keys": {}', $salvaged ) ?? $salvaged;
            $salvaged = preg_replace( '/"removed_keys"\s*:\s*\[\s*\]/', '"removed_keys": {}', $salvaged ) ?? $salvaged;
            $salvaged = preg_replace( '/"changes"\s*:\s*\[\s*\]/', '"changes": {}', $salvaged ) ?? $salvaged;

            if ( preg_match( '/\{.*\}/s', $salvaged, $match ) ) {
                $salvaged = $match[0];
            }

            $decoded = json_decode( $salvaged, true, 512, JSON_THROW_ON_ERROR );
            if ( is_array( $decoded ) ) {
                return $this->normalize_patch_shape( $decoded );
            }

            throw $e;
        }

        throw new \JsonException( 'Claude patch did not decode to an object.' );
    }

    private function normalize_patch_shape( array $patch ): array {
        foreach ( [ 'changes', 'added_keys', 'removed_keys' ] as $section ) {
            if ( isset( $patch[ $section ] ) && ! is_array( $patch[ $section ] ) ) {
                $patch[ $section ] = [];
            }
        }

        return $patch;
    }

    private function guided_fallback_response( array $context, string $trace_id, string $fallback_message ) {
        $response = ClaudeClient::request_with_meta( $this->build_guided_prompt( $context ), false, [
            'trace_id'       => $trace_id,
            'call_mode'      => 'guided_fallback',
            'rule_id'        => sanitize_key( (string) ( $context['id'] ?? '' ) ),
            'prompt_version' => 'guided_v1',
        ] );
        $steps = is_wp_error( $response ) ? '' : ( $response['content'] ?? '' );
        if ( is_wp_error( $steps ) ) {
            $steps = '<p>' . esc_html( $fallback_message ) . '</p>';
        }

        return rest_ensure_response( [
            'guided_fallback' => true,
            'steps'           => $steps,
            'trace_id'        => $trace_id,
        ] );
    }
}
