<?php
namespace Accessibility_Auditor;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class AI {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        

    }

    /**
     * Register custom REST API routes
     */
    public function register_routes() {
        register_rest_route('aa/v1', '/guided-fix', [
            'methods'  => 'POST',
            'callback' => [$this, 'generate_guided_fix'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('aa/v1', '/auto-fix', [
            'methods'  => 'POST',
            'callback' => [$this, 'apply_auto_fix'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('aa/v1', '/save-fix', [
            'methods'  => 'POST',
            'callback' => [$this, 'save_fix'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('aa/v1', '/revert-fix', [
            'methods'  => 'POST',
            'callback' => [$this, 'revert_fix'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    /**
     * Permissions: only editors/admins
     */
    public function check_permissions() {
        return current_user_can('edit_posts');
    }

    /**
     * Generate WCAG step-by-step guidance from Claude
     */

    public function generate_guided_fix( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $context = $params['context'] ?? $params;  // adapt if you wrapped under 'context'



        // $prompt = "You are an accessibility assistant. The site uses Bricks Builder and AutomaticCSS.\n"
        //         . "Provide step-by-step WCAG fix instructions in plain text for this issue:\n"
        //         . json_encode( $context, JSON_PRETTY_PRINT );

        $prompt = "You are an accessibility assistant. The site uses Bricks Builder and AutomaticCSS.\n"
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
                    . json_encode($context, JSON_PRETTY_PRINT);


        $response = $this->call_claude_api( $prompt );


        

        if ( is_wp_error( $response ) ) {
            return rest_ensure_response([
                'error'   => true,
                'message' => $response->get_error_message(),
            ]);
        }

        return rest_ensure_response([
            'steps' => $response,
        ]);
    }



    /**
 * Handle automatic accessibility fixes using Claude + Bricks API.
 */

public function apply_auto_fix(WP_REST_Request $request)
{
    $payload = $request->get_json_params();

    // 1️⃣ Validate input
    $issue = $payload['issue'] ?? null;
    if (!$issue) {
        return new WP_Error('missing_issue', 'Missing issue data.');
    }

    $post_id = intval($payload['post_id'] ?? 0);
    if (!$post_id) {
        return new WP_Error('missing_post', 'post_id is required.', ['status' => 400]);
    }
    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error('forbidden', 'You do not have permission to edit this post.', ['status' => 403]);
    }

    error_log( sprintf( '[AA:auto-fix] START post_id=%d issue_id=%s nodes=%d', $post_id, $issue['id'] ?? '?', count( $issue['nodes'] ?? [] ) ) );

    // 2️⃣ Load Bricks content
    $content = get_post_meta($post_id, BRICKS_DB_PAGE_CONTENT, true);
    $elements = is_array($content) ? $content : json_decode($content, true);

    if (empty($elements) || !is_array($elements)) {
        return new WP_Error('invalid_content', 'Invalid or empty Bricks content.');
    }

    // 3️⃣ Extract relevant Bricks elements for this issue
    $target_elements = $this->extract_bricks_elements_from_issue($elements, $issue);
    error_log( sprintf( '[AA:auto-fix] Bricks elements loaded=%d target_elements=%d', count( $elements ), count( $target_elements ) ) );
    if (empty($target_elements)) {
        error_log( '[AA:auto-fix] No matching Bricks elements found — returning error' );
        return new WP_Error('missing_elements', 'No matching Bricks elements found.');
    }

    // 4️⃣ Save a pre-fix revision (for undo support)
    $this->save_bricks_revision($post_id, $elements, 'pre_fix_backup');

    $applied = [];

    // 5️⃣ Iterate each affected element and fix individually via Claude
    foreach ($target_elements as $element) {


      

        $prompt = sprintf(
            "You are an AI accessibility assistant for WordPress using the Bricks Builder framework and AutomaticCSS.

            You are provided with:
            1️⃣ An accessibility issue (axe-core JSON).
            2️⃣ The Bricks element JSON responsible for that issue.
            3️⃣ The element type name: **%s**

            Your task:
            - Analyze the accessibility issue and generate the *minimal JSON patch* to fix it.
            - If any attribute or key should be removed (e.g., invalid aria, redundant role), include it under `removed_keys` using nested JSON.
            - If new attributes or keys are required, include them under `added_keys`.
            - If existing attributes should be updated, include them under `changes`.
            - Do NOT return the full element — only the patch object.

            ⚙️ Output must include only the minimal patch object in this exact format:

            {
            \"element_id\": \"<same ID as provided>\",
            \"changes\": {
                \"settings\": {
                \"url\": {
                    \"ariaLabel\": \"New label here\"
                },
                \"attributes\": {
                    \"aria-label\": \"New label here\"
                }
                }
            },
            \"added_keys\": {
                \"settings\": {
                \"image\": {
                    \"alt\": \"Descriptive alt text\"
                }
                }
            },
            \"removed_keys\": {
                \"settings\": {
                \"attributes\": {
                    \"role\": true,
                    \"aria-hidden\": true
                }
                }
            }
            }

            📘 Accessibility guidance:
            - Links/buttons → add meaningful aria-labels, remove duplicate or empty attributes.
            - Images → ensure descriptive alt text; remove decorative or empty alts.
            - Iframes/videos → add a title or aria-label; remove redundant attributes.
            - Text/headings → fix tag hierarchy (settings.tag), remove unnecessary roles.
            - Colors → ensure contrast ≥ 4.5:1 by adjusting `settings.style.color` or `_cssGlobalClasses`.

            ⚙️ Output Rules:
            - Must be valid JSON (no markdown, comments, or explanations).
            - Use only nested JSON objects (no dot-notation paths).
            - Must include only changed, added, or removed keys.
            - Do not include unrelated or full Bricks structure.
            - Always include the `element_id` copied exactly from the provided element JSON.

            === Accessibility Issue JSON ===
            %s

            === Bricks Element JSON ===
            %s

            Output only the JSON patch as described above.",
            strtoupper($element['name'] ?? 'UNKNOWN'),
            json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            json_encode($element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        try {
            // 6️⃣ Send prompt to Claude
            error_log( sprintf( '[AA:auto-fix] Calling Claude for element_id=%s type=%s', $element['id'] ?? '?', $element['name'] ?? '?' ) );
            $response = $this->call_claude_api($prompt, true);

            // 7️⃣ Normalize and decode AI output
            $json = $this->normalize_ai_response($response);
            error_log( '[AA:auto-fix] Claude raw (first 300): ' . substr( $json, 0, 300 ) );

           

            try {
                // Attempt strict decoding
                $patch = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

               
                $id = $patch['element_id'] ?? null;
               
                if (!$id) {
                    error_log('[AAI] Skipping patch: no element ID provided');
                    continue;
                }


              
                  $original = $this->find_bricks_element($elements, $id);



                  
                    if (!$original) {
                        error_log("[AAI] Could not find element with ID: {$id}");
                        continue;
                    }

                    // 🧩 Apply AI patch into original element
                    $updated_element = $this->apply_patch_to_bricks_element($original, $patch);

                    
                    // 🧱 Update the element in the full structure
                    if ($this->update_bricks_element($elements, $id, $updated_element)) {
                        $applied[] = $updated_element;
                    } else {
                        error_log("[AAI] Failed to update element {$id} in structure");
                    }
                

            } catch (JsonException $e) {
                error_log("[AAI] JSON decode failed at line " . __LINE__);
                error_log("Bad JSON: " . substr($json, 0, 300));
                error_log("Error message: " . $e->getMessage());

                return new WP_REST_Response([
                    'error'   => true,
                    'message' => 'AI returned invalid JSON.',
                    'details' => $e->getMessage(),
                    'raw'     => substr($json, 0, 300)
                ], 500);
            }

           
            
        } catch (Throwable $e) {
            error_log('[AAI] Error during AI fix: ' . $e->getMessage());
            continue;
        }
    }


    // ✅ 8️⃣ Save updated Bricks content and clear caches
        try {
            // Save updated Bricks structure
            update_post_meta($post_id, BRICKS_DB_PAGE_CONTENT, $elements);

            // Clear Bricks cache (CSS, rendered data)
            if (function_exists('bricks_flush_post_css')) {
                bricks_flush_post_css($post_id);
            }
            if (function_exists('bricks_clear_rendered_data')) {
                bricks_clear_rendered_data($post_id);
            }

            // Optional: Trigger Bricks data refresh on frontend (if using iframe)
            // This can help reflect changes instantly in builder view
            do_action('bricks_after_save_post', $post_id);

            // ✅ 9️⃣ Save revision and changelog
            $revision_key = $this->save_bricks_revision($post_id, $elements, 'ai_fix');
            $changelog    = $this->generate_changelog($applied);

            error_log( sprintf( '[AA:auto-fix] DONE applied=%d revision_key=%s', count( $applied ), $revision_key ?? 'none' ) );

            // ✅ 🔟 Return REST response
            return new WP_REST_Response([
                'success'   => true,
                'message'   => 'Accessibility fixes applied successfully.',
                'changes'   => $applied,
                'revision'  => $revision_key,
                'changelog' => $changelog,
            ]);

        } catch (Throwable $e) {
            error_log('[AAI] Failed to save updated Bricks content: ' . $e->getMessage());
            return new WP_REST_Response([
                'error'   => true,
                'message' => 'Failed to save updated Bricks content.',
                'details' => $e->getMessage(),
            ], 500);
        }
}


/**
 * Accept an auto-fix: logs the acceptance to audit trail.
 * The Bricks content was already saved by apply_auto_fix.
 */
public function save_fix(WP_REST_Request $request)
{
    $payload  = $request->get_json_params();
    $post_id  = intval($payload['post_id'] ?? 0);

    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        return new WP_Error('forbidden', 'Unauthorized.', ['status' => 403]);
    }

    $revision_key = sanitize_text_field($payload['revision_key'] ?? '');

    Revisions::log_autofix($post_id, [
        'revision_key' => $revision_key,
        'action'       => 'accepted',
    ]);

    return rest_ensure_response(['success' => true]);
}


/**
 * Reject an auto-fix: restores Bricks content from the pre-fix revision snapshot.
 */
public function revert_fix(WP_REST_Request $request)
{
    $payload      = $request->get_json_params();
    $post_id      = intval($payload['post_id'] ?? 0);
    $revision_key = sanitize_text_field($payload['revision_key'] ?? '');

    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        return new WP_Error('forbidden', 'Unauthorized.', ['status' => 403]);
    }

    if (!$revision_key) {
        return new WP_Error('missing_revision', 'revision_key is required.', ['status' => 400]);
    }

    $revision_json = get_post_meta($post_id, $revision_key, true);
    if (!$revision_json) {
        return new WP_Error('revision_not_found', 'Revision not found.', ['status' => 404]);
    }

    $revision = json_decode($revision_json, true);
    $elements = $revision['elements'] ?? null;

    if (empty($elements) || !is_array($elements)) {
        return new WP_Error('invalid_revision', 'Revision data is invalid.', ['status' => 500]);
    }

    update_post_meta($post_id, BRICKS_DB_PAGE_CONTENT, $elements);

    if (function_exists('bricks_flush_post_css')) {
        bricks_flush_post_css($post_id);
    }
    if (function_exists('bricks_clear_rendered_data')) {
        bricks_clear_rendered_data($post_id);
    }

    delete_post_meta($post_id, $revision_key);

    return rest_ensure_response(['success' => true, 'message' => 'Fix reverted successfully.']);
}


private function normalize_ai_response($response)
{


    // 🔹 1. Handle WP error and normalize to string
    if (is_wp_error($response)) {
        throw new Exception('Claude API request failed: ' . $response->get_error_message());
    }

    if (is_array($response)) {
        $response = $response['content']
            ?? $response['body']
            ?? $response['message']
            ?? wp_json_encode($response);
    } elseif (is_object($response)) {
        $response = $response->content
            ?? $response->body
            ?? $response->message
            ?? json_encode($response);
    }

    if (!is_string($response)) {
        $response = wp_json_encode($response);
    }

    // 🔹 2. Clean up Claude / AI artifacts
    $response = mb_convert_encoding($response, 'UTF-8', 'UTF-8');
    $response = preg_replace('/[[:cntrl:]&&[^\r\n\t]]/', '', $response); // invisible chars
    $response = preg_replace('/^```(?:json)?\s*/i', '', $response); // remove starting ```json
    $response = preg_replace('/\s*```$/', '', $response); // remove trailing ```
    //$response = preg_replace('/^Output:\s*/i', '', $response); // strip "Output:"
    //$response = preg_replace('/^Here is the corrected JSON[:\s]*/i', '', $response);
    $response = preg_replace('/,(\s*[\]\}])/', '$1', $response); // trailing commas
    $response = trim($response);

    // 🔹 3. Ensure proper bracket closure
    if (str_starts_with($response, '[') && !str_ends_with($response, ']')) {
        $response .= ']';
    } elseif (str_starts_with($response, '{') && !str_ends_with($response, '}')) {
        $response .= '}';
    }

    // 🔹 4. Try to decode safely
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[AAI] JSON decode error: ' . json_last_error_msg());
        error_log('[AAI] Raw response: ' . substr($response, 0, 500));

        // Try to extract valid JSON substring
        if (preg_match('/\{.*\}|\[.*\]/s', $response, $match)) {
            $decoded = json_decode($match[0], true);
        }
    }



    // 🔹 5. If still invalid, throw for higher-level handler
    // if (empty($decoded)) {
    //     error_log("[AAI] Claude returned invalid JSON after cleanup: " . substr($response, 0, 300));

    //     return new \WP_REST_Response([
    //         'error'   => true,
    //         'message' => 'Claude returned invalid or empty JSON after cleanup.',
    //         'raw'     => substr($response, 0, 500)
    //     ], 500);
    //  }

    // 🔹 6. Return normalized JSON string for further merging
    return wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}



/**
 * Apply AI patch data to a Bricks element.
 * Supports nested "changes" and "added_keys" structures (no dot notation).
 */
private function apply_patch_to_bricks_element(array $original, $patch): array
{
    if (is_string($patch)) {
        $patch = json_decode($patch, true);
    }

    if (!is_array($patch)) {
        throw new Exception('Invalid patch format — expected JSON array or object.');
    }

    // 🔹 Apply "changes" by deep-merging directly (nested JSON)
    if (!empty($patch['changes']) && is_array($patch['changes'])) {
        $original = $this->deep_merge_bricks_element($original, $patch['changes']);
    }

    // 🔹 Apply "added_keys" by merging as well
    if (!empty($patch['added_keys']) && is_array($patch['added_keys'])) {
        $original = $this->deep_merge_bricks_element($original, $patch['added_keys']);
    }

    // 🔹 Apply removals (deeply unset based on nested JSON)
    if (!empty($patch['removed_keys']) && is_array($patch['removed_keys'])) {
        $original = $this->deep_remove_bricks_keys($original, $patch['removed_keys']);
    }

    

    // 🔸 If AI didn't include the ID, preserve the original
    if (empty($original['id']) && !empty($patch['element_id'])) {
        $original['id'] = $patch['element_id'];
    }

    // 🧱 Ensure children structure is valid
    if (isset($original['children']) && !is_array($original['children'])) {
        $original['children'] = [];
    }

    return $original;
}


private function deep_remove_bricks_keys(array $original, array $patch): array
{
    foreach ($patch as $key => $value) {
        // If this key should be removed entirely
        if (isset($original[$key]) && $value === true) {
            unset($original[$key]);
            continue;
        }

        // If nested object, recurse deeper
        if (isset($original[$key]) && is_array($value) && is_array($original[$key])) {
            $original[$key] = $this->deep_remove_bricks_keys($original[$key], $value);
        }
    }

    return $original;
}


/**
 * Deep merge arrays recursively (preserves Bricks Builder structure).
 * - Merges nested arrays instead of overwriting them.
 * - Overwrites scalar values.
 */
private function deep_merge_bricks_element(array $original, array $patch): array
{
    foreach ($patch as $key => $value) {
        // Recursively merge arrays
        if (is_array($value) && isset($original[$key]) && is_array($original[$key])) {
            $original[$key] = $this->deep_merge_bricks_element($original[$key], $value);
        } else {
            // Overwrite or add scalar
            $original[$key] = $value;
        }
    }

    // Keep Bricks structure safe
    if (isset($original['children']) && !is_array($original['children'])) {
        $original['children'] = [];
    }

    return $original;
}


private function save_bricks_revision($post_id, $elements, $context = 'auto_fix')
{
    $timestamp = current_time('Y-m-d H:i:s');
    $key = "bricks_revision_{$context}_" . time();

    $revision_data = [
        'timestamp' => $timestamp,
        'context'   => $context,
        'elements'  => $elements,
    ];

    update_post_meta($post_id, $key, wp_json_encode($revision_data));

    return $key;
}


private function extract_bricks_elements_from_issue($elements, $issue)
{
    $targets = [];
    foreach ($issue['nodes'] ?? [] as $node) {
        foreach ($node['target'] ?? [] as $selector) {
            if (preg_match('/#brxe-([a-z0-9]+)/i', $selector, $m)) {
                $targets[] = $m[1];
            }
        }
    }

    $result = [];
    foreach (array_unique($targets) as $id) {
        $el = $this->find_bricks_element($elements, $id);
        if ($el) $result[] = $el;
    }
    return $result;
}









private function update_bricks_element(&$elements, $element_id, $new_data) {

    foreach ($elements as &$el) {
        if (($el['id'] ?? null) === $element_id) {
            $el = $new_data;
            return true;
        }
        if (!empty($el['children'])) {
            if ($this->update_bricks_element($el['children'], $element_id, $new_data)) return true;
        }
    }
    return false;
}




/**
 * Generate a human-readable changelog for audit/log UI.
 */
private function generate_changelog( $applied ) {
    $log = [];
    foreach ( $applied as $el ) {
        $id      = $el['id'] ?? 'unknown';
        $changes = $el['changes']['settings'] ?? [];
        foreach ( $changes as $key => $val ) {
            $display = is_array( $val ) ? wp_json_encode( $val ) : (string) $val;
            $log[] = sprintf( 'Element %s: updated settings.%s → %s', $id, $key, $display );
        }
        if ( empty( $changes ) ) {
            $log[] = sprintf( 'Element %s: patch applied', $id );
        }
    }
    return $log;
}

private function find_bricks_element( $elements, $target_id ) {
    foreach ( $elements as $el ) {
        if ( isset( $el['id'] ) && $el['id'] === $target_id ) {
            return $el;
        }
        if ( ! empty( $el['children'] ) ) {
            $found = $this->find_bricks_element( $el['children'], $target_id );
            if ( $found ) return $found;
        }
    }
    return null;
}





    /**
     * Helper: Call Claude API
     */
    private function call_claude_api( $prompt, $json_mode = false ) {
        $api_key = \Accessibility_Auditor\Settings::getClaudeKey();

        if ( empty( $api_key ) ) {
            return new \WP_Error( 'no_api_key', 'Claude API key is not configured.' );
        }

        $url = "https://api.anthropic.com/v1/messages";

        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ];

        // Pick a default Claude model that most accounts have
        $model = 'claude-sonnet-4-20250514'; 
        // If your account has 3.5 access, swap with: claude-3-5-sonnet-20240620

        $body = [
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ]
            ],
        ];

        if ( $json_mode ) {
            $body['system'] = "Respond ONLY with valid JSON.";
        }

        $args = [
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
            'timeout' => 300,
        ];

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        if ( $status < 200 || $status >= 300 ) {
            return new \WP_Error( 'api_http_error', "HTTP $status: " . substr( $raw_body, 0, 200 ) );
        }

        $decoded = json_decode( $raw_body, true );
        if ( null === $decoded ) {
            return new \WP_Error( 'api_json_decode_error', 'Failed to decode API response: ' . substr( $raw_body, 0, 200 ) );
        }

        // Parse Claude v1/messages response
        $content = '';
        if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
            foreach ( $decoded['content'] as $block ) {
                if ( isset( $block['text'] ) ) {
                    $content .= $block['text'];
                }
            }
        }

        $content = trim( $content );

        if ( $content === '' ) {
            return new \WP_Error( 'api_empty_response', 'API responded but content is empty.' );
        }

        return $content;
    }




}
