<?php
namespace Accessibility_Auditor;

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

        //wp_die( $context);

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

        //$response = $this->list_claude_models();

        

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
     * Auto-fix issues via Claude -> JSON -> Bricks API
     */
        public function apply_auto_fix(WP_REST_Request $request) {
        $issue = $request->get_json_params();

        $prompt = "You are an accessibility assistant. 
        The site uses Bricks Builder and AutomaticCSS.
        Respond ONLY with structured JSON describing changes:
        [
        { \"elementId\": \"brxe-123\", \"setting\": \"ariaLabel\", \"before\": \"\", \"after\": \"Main Navigation\" }
        ]
        Issue: " . json_encode($issue, JSON_PRETTY_PRINT);

        $json = $this->call_claude_api($prompt, true);

        // 🔹 Convert JSON string → PHP array
        $changes = json_decode($json, true);
        if (!is_array($changes)) {
            return new WP_REST_Response([
                'error'   => true,
                'message' => 'Invalid JSON from AI',
                'raw'     => $json
            ], 500);
        }

        // 🔹 Apply each change to Bricks content
        foreach ($changes as $c) {
            $this->apply_bricks_change($c);
        }

        return new WP_REST_Response([
            'success'   => true,
            'changes'   => $changes,
            'changelog' => $this->generate_changelog($changes)
        ]);
    }

    /**
     * Actually update Bricks element data.
     */
    private function apply_bricks_change($change) {
        $element_id = $change['elementId'];
        $setting    = $change['setting'];
        $after      = $change['after'];

        // 1. Load Bricks content (JSON) for the post
        $post_id = 123; // TODO: identify from $change or request
        $content = get_post_meta($post_id, BRICKS_DB_POST_CONTENT, true);
        $data    = json_decode($content, true);

        if (!isset($data['elements'][$element_id])) return false;

        // 2. Apply modification
        $data['elements'][$element_id][$setting] = $after;

        // 3. Save back into Bricks content
        update_post_meta($post_id, BRICKS_DB_POST_CONTENT, wp_json_encode($data));

        return true;
    }


    /**
     * Save applied fixes (revision + changelog entry)
     */
    public function save_fix(WP_REST_Request $request) {
        $issue = $request->get_json_params();

        // TODO: integrate with Bricks revision system
        // Example: create a post meta entry for audit trail
        $log = [
            'user' => get_current_user_id(),
            'time' => current_time('mysql'),
            'issue' => $issue
        ];
        add_option('aa_fix_log_' . time(), $log);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Fix saved & revision logged'
        ]);
    }



    private function list_claude_models() {
        $api_key = Settings::getClaudeKey();
        $url = "https://api.anthropic.com/v1/models";

        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ];

        $response = wp_remote_get( $url, [ 'headers' => $headers ] );

        return wp_remote_retrieve_body( $response );
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
            'max_tokens' => 800,
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
            'timeout' => 60,
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




    /**
     * Helper: Build changelog entries
     */
    private function generate_changelog($changes) {
        $log = [];
        if (!is_array($changes)) return $log;

        foreach ($changes as $c) {
            $log[] = sprintf(
                "Element %s: %s changed from '%s' → '%s'",
                $c['elementId'] ?? 'unknown',
                $c['setting'] ?? 'setting',
                $c['before'] ?? '',
                $c['after'] ?? ''
            );
        }
        return $log;
    }
}
