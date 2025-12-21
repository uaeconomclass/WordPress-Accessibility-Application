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

        $bricks_version = $this->versions['bricks'] ?? 'unknown';
        $acss_version   = $this->versions['acss'] ?? 'unknown';

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
 * Handle automatic accessibility fixes using Claude + Bricks API.
 */
// public function apply_auto_fix( WP_REST_Request $request ) {
   

//     $payload = $request->get_json_params();

//     // Axe issue object
//     $issue = $payload['issue'] ?? null;
//     if ( ! $issue ) {
//         return new WP_Error('missing_issue', 'Missing issue data');
//     }


   
   
//     // Extract target selector (e.g. "#brxe-e3aaf7")
//     $target = $issue['nodes'][0]['target'][0] ?? '';
//     if ( ! $target || strpos($target, '#brxe-') === false ) {
//         return new WP_Error('invalid_target', 'No Bricks element ID found in target selector');
//     }

//     // Normalize element ID ("brxe-e3aaf7" → "e3aaf7")
//     $element_id = str_replace('#brxe-', '', $target);



   

//      global $post;
//      $post_id = $post->ID ?? url_to_postid( $_SERVER['HTTP_REFERER'] ?? '' );


//     if ( ! $post_id ) {
//         return new WP_Error( 'missing_post', 'Cannot detect post ID.', [ 'status' => 400 ] );
//     }


//      $content = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
//      $elements = is_array( $content ) ? $content : json_decode( $content, true );

//     if ( empty( $elements ) || ! is_array( $elements ) ) {
//         return false;
//     }

    
   
   
//    // $issue = $request->get_json_params();


//     $target_element = $this->find_bricks_element( $elements, $element_id ); // custom helper below

   
     
//     //wp_die( $element_data );

   

//         //  $prompt = "You are an accessibility assistant for WordPress. "
//         //     . "The site uses Bricks Builder and AutomaticCSS. "
//         //     . "Your task: automatically fix accessibility issues via structured JSON. "
//         //     . "Never modify raw PHP, theme files, or HTML markup directly.\n\n"
//         //     . "Respond ONLY with a JSON array of objects, each describing a change:\n"
//         //     . "Rules:\n"
//         //     . "- Each object must include: elementId, setting, before, after.\n"
//         //     . "- Changes must refer to Bricks element settings or AutomaticCSS class names only.\n"
//         //     . "- Never suggest edits to PHP, CSS, or JS files.\n"
//         //     . "- Each change must be minimal and reversible.\n"
//         //     . "- If you cannot determine a safe fix, return an empty array []\n\n"
//         //     . "Accessibility issue to fix:\n"
//         //     . json_encode( $issue, JSON_PRETTY_PRINT );


//         $prompt = sprintf(
//             "You are an AI accessibility assistant for WordPress.\n".
//             "The site uses Bricks Builder and AutomaticCSS.\n".
//             "You are provided with:\n".
//             "1️⃣ An accessibility issue detected by axe-core.\n".
//             "2️⃣ The corresponding Bricks element JSON.\n\n".
//             "Your task:\n".
//             "- Fix the accessibility issue according to WCAG 2.1 and axe-core best practices.\n".
//             "- Modify the element JSON only — no explanations.\n".
//             "- Respect Bricks Builder JSON structure and naming conventions.\n".
//             "- For link-related elements (image, button, text link, etc.), set the label in BOTH places:\n".
//             "  • settings.url.ariaLabel (Bricks-specific)\n".
//             "  • settings.attributes['aria-label'] (HTML standard)\n".
//             "- For images, ensure alt text is present in settings.image.alt or settings.attributes['aria-label'].\n".
//             "- For headings or text, ensure proper semantic tags (e.g., h1–h6) and clarity.\n".
//             "- For color/contrast issues, apply AutomaticCSS utility classes (e.g., 'color-contrast' or 'acss-hide-text').\n".
//             "- Never return code or text outside valid JSON.\n\n".
//             "=== Accessibility Issue ===\n%s\n\n".
//             "=== Bricks Element JSON ===\n%s\n\n".
//             "Output only the corrected Bricks element JSON.",
//             json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
//             json_encode($target_element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
//         );



      
//         // ---------------------------------------------------------------------
//         // 1. Call Claude API
//         // ---------------------------------------------------------------------
//         $json = $this->call_claude_api( $prompt, true );


       
//         // ---------------------------------------------------------------------
//         // 2. Handle WP_Error or unexpected structure
//         // ---------------------------------------------------------------------
//         if ( is_wp_error( $json ) ) {
//             return new WP_REST_Response([
//                 'error'   => true,
//                 'message' => 'Claude API request failed',
//                 'details' => $json->get_error_message(),
//             ], 500);
//         }

//         // Some API wrappers might return arrays with "message" key
//         if ( is_array( $json ) && isset( $json['message'] ) ) {
//             $json = $json['message'];
//         }

//         // ---------------------------------------------------------------------
//         // 3. Clean Claude’s Markdown / wrapped JSON formats
//         // ---------------------------------------------------------------------
//         if ( is_string( $json ) ) {
//             $json = trim( $json );

//             // Remove Markdown code fences (```json … ```)
//             $json = preg_replace( '/^```(?:json)?/i', '', $json );
//             $json = preg_replace( '/```$/', '', $json );

//             // Remove extra HTML wrapping (from wp_die)
//             $json = wp_strip_all_tags( $json );

//             $json = trim( $json );
//         }

//         // ---------------------------------------------------------------------
//         // 4. Decode JSON safely with try/catch
//         // ---------------------------------------------------------------------
//         try {
//             $changes = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
//         } catch ( Exception $e ) {
//             return new WP_REST_Response([
//                 'error'   => true,
//                 'message' => 'Invalid JSON from AI',
//                 'raw'     => is_string( $json ) ? $json : '',
//                 'details' => $e->getMessage(),
//             ], 500);
//         }

//         // ---------------------------------------------------------------------
//         // 5. Validate structure
//         // ---------------------------------------------------------------------
//         if ( ! is_array( $changes ) ) {
//             return new WP_REST_Response([
//                 'error'   => true,
//                 'message' => 'Invalid format returned from AI (not an array)',
//                 'raw'     => $json,
//             ], 500);
//         }



//     wp_die( $changes);

//     // ---------------------------------------------------------------------
//     // 3. Apply each change safely
//     // ---------------------------------------------------------------------
//     $applied = [];
//     foreach ( $changes as $c ) {
//         $success = $this->update_bricks_element( $elements, $element_id, $c );
//         if ( $success ) {
//             $applied[] = $c;
//         }
//     }

//     // ---------------------------------------------------------------------
//     // 4. Save revision & changelog
//     // ---------------------------------------------------------------------
//     //$revision_id = $this->save_bricks_revision( $applied );
//     //$changelog   = $this->generate_changelog( $applied );

//     // ---------------------------------------------------------------------
//     // 5. Return summary
//     // ---------------------------------------------------------------------
//     return new WP_REST_Response([
//         'success'   => true,
//         'changes'   => $applied,
//     ]);
// }



// public function apply_auto_fix( WP_REST_Request $request ) {

//     $payload = $request->get_json_params();

//     // 1️⃣ Validate and extract the issue object
//     $issue = $payload['issue'] ?? null;
//     if ( ! $issue ) {
//         return new WP_Error('missing_issue', 'Missing issue data');
//     }

//     global $post;
//     $post_id = $post->ID ?? url_to_postid( $_SERVER['HTTP_REFERER'] ?? '' );

//     if ( ! $post_id ) {
//         return new WP_Error('missing_post', 'Cannot detect post ID.', [ 'status' => 400 ]);
//     }

//     // 2️⃣ Load the Bricks content JSON from post meta
//     $content  = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
//     $elements = is_array( $content ) ? $content : json_decode( $content, true );

//     if ( empty( $elements ) || ! is_array( $elements ) ) {
//         return new WP_Error('invalid_content', 'Invalid or empty Bricks content.');
//     }

//     $target_elements = $this->extract_bricks_elements_from_issue( $elements, $issue );


//     // 3️⃣ Extract all Bricks element IDs from axe-core issue “targets”
//     // $targets = [];
//     // foreach ( $issue['nodes'] ?? [] as $node ) {
//     //     foreach ( $node['target'] ?? [] as $selector ) {
//     //         if ( preg_match( '/#brxe-([a-z0-9]+)/i', $selector, $m ) ) {
//     //             $targets[] = $m[1];
//     //         }
//     //     }
//     // }

//     // if ( empty( $targets ) ) {
//     //     return new WP_Error('no_targets', 'No valid Bricks element IDs found in issue.');
//     // }

//     // // 4️⃣ Collect all target Bricks elements for AI prompt
//     // $target_elements = [];
//     // foreach ( $targets as $id ) {
//     //     $el = $this->find_bricks_element( $elements, $id );
//     //     if ( $el ) {
//     //         $target_elements[] = $el;
//     //     }
//     // }

//     if ( empty( $target_elements ) ) {
//         return new WP_Error('missing_elements', 'No matching Bricks elements found in post.');
//     }

//     error_log( print_r($target_elements) );

//     wp_die("please");

//     // 5️⃣ Build multi-element AI prompt
//     // $prompt = sprintf(
//     //     "You are an AI accessibility assistant for WordPress.\n".
//     //     "The site uses Bricks Builder and AutomaticCSS.\n".
//     //     "You are provided with:\n".
//     //     "1️⃣ An accessibility issue detected by axe-core.\n".
//     //     "2️⃣ One or more related Bricks element JSON objects.\n\n".
//     //     "Your task:\n".
//     //     "- Fix the accessibility issue according to WCAG 2.1 and axe-core best practices.\n".
//     //     "- Modify only the provided Bricks elements — no explanations or text outside JSON.\n".
//     //     "- Output must be a JSON array of corrected Bricks elements.\n".
//     //     "- Respect Bricks Builder JSON structure and naming conventions.\n".
//     //     "- For link-related elements (image, button, text link, etc.), set label in BOTH places:\n".
//     //     "  • settings.url.ariaLabel (Bricks-specific)\n".
//     //     "  • settings.attributes['aria-label'] (HTML standard)\n".
//     //     "- For images, ensure alt text in settings.image.alt or settings.attributes['aria-label'].\n".
//     //     "- For headings/text, ensure proper semantic tags (h1–h6).\n".
//     //     "- For color/contrast, use AutomaticCSS utility classes (e.g., 'color-contrast').\n".
//     //     "- Never include markdown, code fences, or text outside valid JSON.\n\n".
//     //     "=== Accessibility Issue ===\n%s\n\n".
//     //     "=== Bricks Elements JSON (array) ===\n%s\n\n".
//     //     "Output: Only the corrected Bricks element(s) JSON". 
//     //     "- Do NOT include markdown code fences (```), language labels, or extra text".
//     //     "- The output must be ONLY valid JSON text starting with '[' or '{' and ending properly.",
//     //     json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
//     //     json_encode($target_elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
//     // );


//     $prompt = sprintf(
//         "You are an AI accessibility assistant for WordPress.\n" .
//         "The site uses Bricks Builder and AutomaticCSS.\n" .
//         "You are provided with:\n" .
//         "1️⃣ An accessibility issue detected by axe-core.\n" .
//         "2️⃣ One or more related Bricks element JSON objects.\n\n" .
//         "Your task:\n" .
//         "- Fix the accessibility issue according to the Web Content Accessibility Guidelines (WCAG) — including WCAG 2.0, 2.1, 2.2 best practices.\n" .
//         "- Apply fixes consistent with axe-core, ARIA Authoring Practices, and modern accessibility standards.\n" .
//         "- Modify only the provided Bricks elements — do not add or remove unrelated elements.\n" .
//         "- Output must be a valid JSON array of corrected Bricks elements.\n" .
//         "- Respect Bricks Builder JSON structure, keys, and naming conventions exactly.\n\n" .
//         "Specific rules:\n" .
//         "- For link-related elements (image, button, text link, etc.):\n" .
//         "  • Set accessible labels in BOTH:\n" .
//         "    - settings.url.ariaLabel (Bricks-specific)\n" .
//         "    - settings.attributes['aria-label'] (HTML standard)\n" .
//         "- For images:\n" .
//         "  • Always include descriptive alt text in settings.image.alt or settings.attributes['aria-label'].\n" .
//         "- For text or headings:\n" .
//         "  • Use correct semantic tags (h1–h6, p, etc.) according to document structure.\n" .
//         "- For color and contrast:\n" .
//         "  • Ensure compliance with WCAG minimum contrast ratios.\n" .
//         "  • Use AutomaticCSS utility classes where applicable (e.g., 'color-contrast', 'bg-contrast').\n" .
//         "- For focus states and interactive elements:\n" .
//         "  • Maintain visible focus and keyboard accessibility.\n" .
//         "- Do NOT include markdown, explanations, or code fences (```json, ``` etc.).\n" .
//         "- Output must be only valid JSON starting with '[' or '{' and ending with ']' or '}'.\n" .
//         "- Ensure there are no missing brackets, no trailing commas, and the JSON is well-formed.\n\n" .
//         "=== Accessibility Issue ===\n%s\n\n" .
//         "=== Bricks Elements JSON (array) ===\n%s\n\n" .
//         "Output: Only the corrected Bricks element(s) JSON.",
//         json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
//         json_encode($target_elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
//     );



   


//      try {
//     // 🧠 STEP 1: Call the Claude API
//     $json = $this->call_claude_api($prompt, true);

   

//     // 🪵 STEP 2: Log raw API result type + short preview
//     //error_log('[AAI] Claude raw type: ' . gettype($json));
//     //error_log('[AAI] Claude raw dump (first 500 chars): ' . $json);

//     // 🚨 STEP 3: Handle low-level WordPress transport errors
//     if (is_wp_error($json)) {
//         return new WP_REST_Response([
//             'error'   => true,
//             'message' => 'Claude API request failed.',
//             'details' => $json->get_error_message(),
//             'context' => 'Transport or authentication issue',
//         ], 500);
//     }


    

//     // 🧩 STEP 4: Normalize the return type
//     if (is_array($json)) {
//         if (isset($json['message']) && is_string($json['message'])) {
//             $json = $json['message'];
//         } elseif (isset($json['content']) && is_string($json['content'])) {
//             $json = $json['content'];
//         } else {
//             // Fallback: convert array to JSON text
//             $json = wp_json_encode($json);
//         }
//     } elseif (is_object($json)) {
//         if (isset($json->body)) {
//             $json = $json->body;
//         } elseif (isset($json->content)) {
//             $json = $json->content;
//         } elseif (isset($json->message)) {
//             $json = $json->message;
//         } else {
//             $json = json_encode($json);
//         }
//     }

//         if (is_string($json)) {
//         // Normalize and strip formatting
//         $json = mb_convert_encoding($json, 'UTF-8', 'UTF-8');
//         $json = preg_replace('/[[:cntrl:]&&[^\r\n\t]]/', '', $json);
//         $json = preg_replace('/^```(?:json)?\s*/i', '', $json);
//         $json = preg_replace('/\s*```$/', '', $json);
//         $json = trim($json);

//         // ✅ Try to auto-fix common AI truncation issues
//         // If it starts with [ but doesn’t end with ], add ]
//         if (str_starts_with($json, '[') && !str_ends_with($json, ']')) {
//             $json .= ']';
//         }

//         // If it starts with { but doesn’t end with }, add }
//         if (str_starts_with($json, '{') && !str_ends_with($json, '}')) {
//             $json .= '}';
//         }

//         // Remove dangling commas before closing brackets
//         $json = preg_replace('/,(\s*[\]\}])/', '$1', $json);
//     }

//      error_log(gettype($json));
//      error_log( $json );

//     // 🧠 STEP 7: Finally, safely decode JSON
//     try {
//         $changes = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
//     } catch (JsonException $e) {
//         error_log('[AAI] JSON decode failed at line ' . __LINE__ . ': ' . $e->getMessage());
//         error_log('[AAI] Raw JSON preview: ' . substr($json, 0, 500));

//         return new WP_REST_Response([
//             'error'   => true,
//             'message' => 'Invalid JSON returned from Claude (encoding or control character issue)',
//             'raw'     => substr($json, 0, 500),
//             'details' => $e->getMessage(),
//         ], 500);
//     }

   

// } catch (Throwable $e) {
//     // 🧯 Global safety catch — logs any unexpected issue
//     error_log('[AAI] Fatal error during auto-fix: ' . $e->getMessage());
//     error_log($e->getTraceAsString());

//     return new WP_REST_Response([
//         'error'   => true,
//         'message' => 'Unexpected server error while processing AI response.',
//         'details' => $e->getMessage(),
//     ], 500);
// }




       

//         // ✅ STEP 7: Normalize into array form
//         if (!is_array($changes)) {
//             $changes = [$changes];
//         }


//         wp_die( $changes );
    
//         // 🔟 Apply each AI fix
//         $applied = [];
//         foreach ( $changes as $c ) {
//             $id = $c['id'] ?? null;
//             if ( ! $id ) continue;
//             $success = $this->update_bricks_element( $elements, $id, $c );
//             if ( $success ) {
//                 $applied[] = $c;
//             }
//         }

//         // Save updated Bricks content
//         update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $elements );

//         if ( function_exists( 'bricks_flush_post_css' ) ) {
//             bricks_flush_post_css( $post_id ); // Clears CSS cache for the post
//         }

//         if ( function_exists( 'bricks_clear_rendered_data' ) ) {
//             bricks_clear_rendered_data( $post_id ); // Clears rendered HTML cache
//         }

//         $revision = $this->save_bricks_revision( $post_id, $elements, $applied );
//         $changelog = $this->generate_changelog( $applied );

//         // Return success
//         return new WP_REST_Response([
//             'success' => true,
//             'changes' => $applied,
//             'revision'  => $revision,
//             'changelog' => $changelog,
//         ]);

//    // 6️⃣ Call Claude API — this function should return either a string or an array depending on how your integration is written.
//         // $json = $this->call_claude_api($prompt, true);

//         // //return print_r($json, true);
//         // error_log(print_r($json, true));

      
//         // // 🔒 STEP 1: Handle transport or API-level errors
//         // if (is_wp_error($json)) {
//         //     return new WP_REST_Response([
//         //         'error'   => true,
//         //         'message' => 'Claude API request failed.',
//         //         'details' => $json->get_error_message(),
//         //         'context' => 'API transport or authentication error',
//         //     ], 500);
//         // }

//         // // 🔍 STEP 2: Normalize Claude’s return structure
//         // // Some API wrappers return { "message": "JSON response" } or other data formats
//         // if (is_array($json)) {
//         //     if (isset($json['message']) && is_string($json['message'])) {
//         //         $json = $json['message'];
//         //     } elseif (isset($json['content']) && is_string($json['content'])) {
//         //         $json = $json['content'];
//         //     } else {
//         //         // Fallback: Convert array to JSON text if it looks like an element set
//         //         $json = wp_json_encode($json);
//         //     }
//         // }

//         // // 🧹 STEP 3: Clean up any Markdown, code fences, or extraneous HTML tags
//         // if (is_string($json)) {
//         //     // Normalize encoding
//         //     $json = mb_convert_encoding($json, 'UTF-8', 'UTF-8');

//         //     // Remove invisible control chars except tab, newline, carriage return
//         //     $json = preg_replace('/[[:cntrl:]&&[^\r\n\t]]/', '', $json);

//         //     // Fix unescaped line breaks in JSON strings (common from AI responses)
//         //     $json = preg_replace("/(?<!\\\\)\n/", "\\n", $json);
//         // }


//         // if (is_object($json)) {
//         //     if (isset($json->body)) {
//         //         $json = $json->body;
//         //     } elseif (isset($json->content)) {
//         //         $json = $json->content;
//         //     } elseif (isset($json->message)) {
//         //         $json = $json->message;
//         //     } else {
//         //         $json = json_encode($json); // last resort
//         //     }
//         // }

//         // // 🧠 Decode safely
//         // try {
//         //     $changes = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
//         // } catch (JsonException $e) {
//         //     // Log the bad JSON for debugging
//         //     error_log("[AAI] JSON decode failed at line " . __LINE__);
//         //     error_log("Raw JSON preview: " . substr($json, 0, 500));

//         //     return new WP_REST_Response([
//         //         'error'   => true,
//         //         'message' => 'Invalid JSON returned from AI (encoding or control character issue)',
//         //         'raw'     => substr($json, 0, 500),
//         //         'details' => $e->getMessage(),
//         //     ], 500);
//         // }

//     // 9️⃣ Normalize structure: force into array if single element
//     // if ( ! is_array( $changes ) || ! isset( $changes[0] ) ) {
//     //     $changes = [ $changes ];
//     // }


//     // wp_die( $changes );
    
//     // // 🔟 Apply each AI fix
//     // $applied = [];
//     // foreach ( $changes as $c ) {
//     //     $id = $c['id'] ?? null;
//     //     if ( ! $id ) continue;
//     //     $success = $this->update_bricks_element( $elements, $id, $c );
//     //     if ( $success ) {
//     //         $applied[] = $c;
//     //     }
//     // }

//     // // Save updated Bricks content
//     // update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $elements );

//     // if ( function_exists( 'bricks_flush_post_css' ) ) {
//     //     bricks_flush_post_css( $post_id ); // Clears CSS cache for the post
//     // }

//     // if ( function_exists( 'bricks_clear_rendered_data' ) ) {
//     //     bricks_clear_rendered_data( $post_id ); // Clears rendered HTML cache
//     // }

//     // $revision = $this->save_bricks_revision( $post_id, $elements, $applied );
//     // $changelog = $this->generate_changelog( $applied );

//     // // Return success
//     // return new WP_REST_Response([
//     //     'success' => true,
//     //     'changes' => $applied,
//     //     'revision'  => $revision,
//     //     'changelog' => $changelog,
//     // ]);
// }


public function apply_auto_fix(WP_REST_Request $request)
{
    $payload = $request->get_json_params();

    // 1️⃣ Validate input
    $issue = $payload['issue'] ?? null;
    if (!$issue) {
        return new WP_Error('missing_issue', 'Missing issue data.');
    }

    global $post;
    $post_id = $post->ID ?? url_to_postid($_SERVER['HTTP_REFERER'] ?? '');
    if (!$post_id) {
        return new WP_Error('missing_post', 'Cannot detect post ID.', ['status' => 400]);
    }

    // 2️⃣ Load Bricks content
    $content = get_post_meta($post_id, BRICKS_DB_PAGE_CONTENT, true);
    $elements = is_array($content) ? $content : json_decode($content, true);

    if (empty($elements) || !is_array($elements)) {
        return new WP_Error('invalid_content', 'Invalid or empty Bricks content.');
    }

    // 3️⃣ Extract relevant Bricks elements for this issue
    $target_elements = $this->extract_bricks_elements_from_issue($elements, $issue);
    if (empty($target_elements)) {
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



        // $prompt = sprintf(
        //     "You are an **AI accessibility assistant** for WordPress using the **Bricks Builder** framework.

        //     You are provided with:
        //     1️⃣ An accessibility issue (axe-core JSON).
        //     2️⃣ The Bricks element JSON responsible for that issue.
        //     3️⃣ The element type name: **%s**

        //     Your task:
        //     - Analyze the issue and propose the *minimal JSON-level patch* required to fix it.
        //     - Do NOT return the full element — only the JSON keys that must be added or updated.
        //     - Output must be a single valid JSON object, starting with '{' and ending with '}'.
        //     - Always use nested JSON objects (no dot-notation paths).
        //     - Always include the exact Bricks element ID under the key `element_id`.

        //     🧱 Bricks element context:
        //     - `name`: the Bricks element type (e.g., image, video, text-basic, button)
        //     - `settings`: stores attributes, URLs, and style
        //     - For example:
        //         • **image** → fix `settings.image.alt`
        //         • **video / iframe** → fix `settings.attributes.title` or `aria-label`
        //         • **link / button** → fix `settings.attributes.aria-label`
        //         • **text-basic / heading** → fix semantic tag in `settings.tag`
        //         • **color / contrast** → fix `settings.style.color` or add `_cssGlobalClasses` minimally

        //     📘 Accessibility guidance:
        //     - Always ensure visible elements are perceivable (labels, alt, title).
        //     - Maintain minimal and valid structure consistent with Bricks Builder JSON.

        //     ⚙️ Output rules:
        //     - Must be valid JSON (no markdown, comments, or prose).
        //     - Must only include the minimal patch.
        //     - Do not include unrelated or global style changes unless explicitly needed.

        //     📦 Example output format:
        //     {
        //     \"element_id\": \"7266b4\",
        //     \"changes\": {
        //         \"settings\": {
        //         \"attributes\": { \"aria-label\": \"Play introduction video\" }
        //         }
        //     },
        //     \"added_keys\": {
        //         \"settings\": {
        //         \"attributes\": { \"title\": \"Company intro video player\" }
        //         }
        //     }
        //     }

        //     === Accessibility Issue JSON ===
        //     %s

        //     === Bricks Element JSON ===
        //     %s

        //     Output only the JSON patch object as described above.",
        //     strtoupper($element_name), // 👈 Pass the element name in human-readable form
        //     json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        //     json_encode($element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        // );


        // $prompt = sprintf(
        //     "You are an AI accessibility assistant for WordPress using the Bricks Builder framework.

        //     You are provided with:
        //     1️⃣ An accessibility issue (axe-core JSON).
        //     2️⃣ The Bricks element JSON responsible for that issue.

        //     Your task:
        //     - Analyze the accessibility issue and propose the *minimal JSON-level patch* required to fix it.
        //     - Do NOT return the full element — only the JSON keys that must be added or updated.
        //     - Output must be a single valid JSON object, starting with '{' and ending with '}'.
        //     - Always use nested JSON objects (no dot-notation paths).
        //     - Always include the exact Bricks element ID under the key `element_id` (copy from the provided element JSON).

        //     📦 Example output format (for illustration):
        //     {
        //     \"element_id\": \"7266b4\",
        //     \"changes\": {
        //         \"settings\": {
        //         \"url\": { \"ariaLabel\": \"New label here\" },
        //         \"attributes\": { \"aria-label\": \"New label here\" }
        //         }
        //     },
        //     \"added_keys\": {
        //         \"settings\": {
        //         \"image\": { \"alt\": \"Descriptive alt text\" }
        //         }
        //     }
        //     }

        //     📘 Accessibility guidance:
        //     - Links and buttons → add accessible labels (aria-label, ariaLabel).
        //     - Images → add descriptive alt text (settings.image.alt).
        //     - Text/Headings → ensure proper semantic tags (p, h2, etc.).
        //     - Colors → ensure contrast ≥ 4.5:1 by adjusting `settings.style.color` or `settings._cssGlobalClasses`.

        //     ⚙️ Output rules:
        //     - Must be valid JSON (no markdown, no ``` fences, no explanations).
        //     - Must only include the minimal patch (keys to add or change).
        //     - Do not include unrelated keys or the full Bricks structure.

        //     === Accessibility Issue JSON ===
        //     %s

        //     === Bricks Element JSON ===
        //     %s

        //     Output: Only the JSON patch object as described above.",
        //     json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        //     json_encode($element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        //     );


        

        // $prompt = sprintf(
        //     "You are an AI accessibility assistant for WordPress.\n" .
        //     "The site uses Bricks Builder and AutomaticCSS.\n\n" .
        //     "You are provided with:\n" .
        //     "1️⃣ An accessibility issue (axe-core JSON).\n" .
        //     "2️⃣ The Bricks element JSON responsible for that issue.\n\n" .
        //     "Your task:\n" .
        //     "- Analyze the accessibility issue and propose the minimal JSON-level changes required to fix it.\n" .
        //     "- Do NOT return the entire element JSON. Instead, return only the keys and values that need to be added or updated.\n" .
        //     "- Follow WCAG 2.0, 2.1, 2.2, and draft 3.0 standards, plus ARIA Authoring Practices and axe-core guidance.\n\n" .
        //     "=== Accessibility Rules ===\n" .
        //     "- 🔗 Links (images, buttons, text links):\n" .
        //     "  • Add or update accessible labels in BOTH:\n" .
        //     "    - settings.url.ariaLabel (Bricks-specific)\n" .
        //     "    - settings.attributes['aria-label'] (HTML standard)\n" .
        //     "- 🖼️ Images:\n" .
        //     "  • Provide concise, descriptive alt text in settings.image.alt or settings.attributes['aria-label'].\n" .
        //     "  • Avoid words like 'image of'.\n" .
        //     "- 🧱 Headings/Text:\n" .
        //     "  • Ensure correct semantic tags (h1–h6, p, span) and hierarchy.\n" .
        //     "- 🎨 Color & Contrast:\n" .
        //     "  • Maintain WCAG AA contrast ratios (4.5:1 normal, 3:1 large).\n" .
        //     "  • Use AutomaticCSS utility classes like 'color-contrast' or 'bg-contrast'.\n" .
        //     "  • Only use valid ARIA attributes when needed (no redundant roles).\n\n" .
        //     "⚙️ Output Format:\n" .
        //     "- Output must be valid JSON, **not markdown** (no ```json fences, no text outside JSON).\n" .
        //     "- Output must start with '{' and end with '}'.\n" .
        //     "- Output must start with '{' and end with '}'.\n" .
        //     "- Output must include only the minimal patch object in this exact format:\n\n" .
        //     "{\n" .
        //     "  \"element_id\": \"<same ID as provided>\",\n" .
        //     "  \"changes\": {\n" .
        //     "    \"settings\": {\n" .
        //     "      \"url\": {\n" .
        //     "        \"ariaLabel\": \"New label here\"\n" .
        //     "      },\n" .
        //     "      \"attributes\": {\n" .
        //     "        \"aria-label\": \"New label here\"\n" .
        //     "      }\n" .
        //     "    }\n" .
        //     "  },\n" .
        //     "  \"added_keys\": {\n" .
        //     "    \"settings\": {\n" .
        //     "      \"image\": {\n" .
        //     "        \"alt\": \"Descriptive alt text\"\n" .
        //     "      }\n" .
        //     "    }\n" .
        //     "  }\n" .
        //     "}\n\n" .
        //     "- Always use properly nested JSON objects instead of dot notation.\n" .
        //     "- Never include markdown, code fences, or comments.\n" .
        //     "Do not include any unrelated keys or full structures.\n\n" .
        //     "=== Accessibility Issue JSON ===\n%s\n\n" .
        //     "=== Bricks Element JSON ===\n%s\n\n" .
        //     "Output: Only the JSON patch object as described above.",
        //     json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        //     json_encode($element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        // );


        try {
            // 6️⃣ Send prompt to Claude
            $response = $this->call_claude_api($prompt, true);

            // 7️⃣ Normalize and decode AI output
            $json = $this->normalize_ai_response($response);

           //wp_die( $json );
           

            try {
                // Attempt strict decoding
                $patch = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

               
                $id = $patch['element_id'] ?? null;
               
                if (!$id) {
                    error_log('[AAI] Skipping patch: no element ID provided');
                    continue;
                }


              
                  $original = $this->find_bricks_element_by_id($elements, $id);


                   //wp_die( $original );

                  
                    if (!$original) {
                        error_log("[AAI] Could not find element with ID: {$id}");
                        continue;
                    }

                    // 🧩 Apply AI patch into original element
                    $updated_element = $this->apply_patch_to_bricks_element($original, $patch);

                   // wp_die($original);
                    
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


    //wp_die( $decoded );

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



private function find_bricks_element_by_id(array $elements, string $id)
{
    foreach ($elements as $el) {
        if (($el['id'] ?? null) === $id) {
            return $el;
        }

        if (!empty($el['children'])) {
            $found = $this->find_bricks_element_by_id($el['children'], $id);
            if ($found) {
                return $found;
            }
        }
    }
    return null;
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


/**
 * (Optional Legacy Utility)
 * Keep this only for backward compatibility with older AI patches that still use dot notation.
 */
private function set_value_by_path(array &$array, string $path, $value): void
{
    $keys = explode('.', $path);
    $ref  = &$array;

    foreach ($keys as $key) {
        if (!isset($ref[$key]) || !is_array($ref[$key])) {
            $ref[$key] = [];
        }
        $ref = &$ref[$key];
    }

    $ref = $value;
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
        if (!empty($el['elements'])) {
            if ($this->update_bricks_element($el['elements'], $element_id, $new_data)) return true;
        }
    }
    return false;
}




/**
 * Generate a human-readable changelog for audit/log UI.
 */
private function generate_changelog( $changes ) {
    $log = [];

    foreach ( $changes as $c ) {
        $element = $c['id'] ?? $c['elementId'] ?? 'unknown';
        $setting = $c['setting'] ?? 'unknown';
        $before  = is_scalar( $c['before'] ) ? $c['before'] : json_encode( $c['before'] );
        $after   = is_scalar( $c['after'] ) ? $c['after'] : json_encode( $c['after'] );

        $log[] = sprintf(
            'Element %s: changed "%s" from "%s" → "%s"',
            $element,
            $setting,
            $before,
            $after
        );
    }

    return $log;
}



private function get_bricks_element_data( $post_id, $element_id ) {
    $content = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );

    $content = json_decode( $content, true );

    if ( ! is_array( $content ) ) {
        return null;
    }

    return $this->find_bricks_element( $content, $element_id );
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
 * Apply a single Bricks setting change.
 */
private function apply_bricks_change( $change ) {
    $element_id = $change['elementId'] ?? '';
    $setting    = $change['setting'] ?? '';
    $after      = $change['after'] ?? '';

    if ( ! $element_id || ! $setting ) {
        return false;
    }

    global $post;
    $post_id = $post->ID ?? url_to_postid( $_SERVER['HTTP_REFERER'] ?? '' );

    if ( ! $post_id ) {
        return new WP_Error( 'missing_post', 'Cannot detect post ID.', [ 'status' => 400 ] );
    }

    $content = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
    $data = is_array( $content ) ? $content : json_decode( $content, true );

    if ( empty( $data ) || ! is_array( $data ) ) {
        return false;
    }

    // Normalize element ID (strip "brxe-")
    $normalized_eid = str_replace( 'brxe-', '', $element_id );

    // 🔹 Recursive search & update
    $updated = $this->recursive_bricks_update( $data, $normalized_eid, $setting, $after );

    if ( ! $updated ) {
        error_log("Accessibility Auditor: Element ID {$normalized_eid} not found in Bricks data.");
        return false;
    }

    // 🔹 Save back
    update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $data );

    // Optionally clear Bricks cache (important for real-time update)
    if ( function_exists( 'bricks_flush_post_css' ) ) {
        bricks_flush_post_css( $post_id );
    }

    return true;
}

/**
 * Recursive search & update in Bricks elements.
 */
private function recursive_bricks_update( &$elements, $element_id, $setting, $after ) {
    foreach ( $elements as &$el ) {
        if ( isset( $el['id'] ) && $el['id'] === $element_id ) {
            if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
                $el['settings'] = [];
            }

            $el['settings'][ $setting ] = $after;

            error_log("Accessibility Auditor: Updated {$setting} for element {$element_id} to {$after}");
            return true;
        }

        // Recursive search in child elements
        if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
            if ( $this->recursive_bricks_update( $el['elements'], $element_id, $setting, $after ) ) {
                return true;
            }
        }
    }
    return false;
}


/**
 * Save a new revision of Bricks content for rollback.
 */
// private function save_bricks_revision( $changes ) {
//      global $post;
//     $post_id = $post->ID ?? url_to_postid( $_SERVER['HTTP_REFERER'] ?? '' );


//    if ( ! $post_id ) {
//     return new WP_Error( 'missing_post', 'Cannot detect post ID.', [ 'status' => 400 ] );
//    }

//     $rev_id = wp_save_post_revision( $post_id );
//     add_post_meta( $rev_id, '_aa_auto_fix_changes', wp_json_encode( $changes ) );

//     return $rev_id;
// }

/**
 * Generate human-readable changelog entries.
 */
// private function generate_changelog( $changes ) {
//     $log = [];
//     foreach ( (array) $changes as $c ) {
//         $log[] = sprintf(
//             "Element %s → %s: '%s' → '%s'",
//             esc_html( $c['elementId'] ?? 'unknown' ),
//             esc_html( $c['setting'] ?? 'setting' ),
//             esc_html( $c['before'] ?? '' ),
//             esc_html( $c['after'] ?? '' )
//         );
//     }
//     return $log;
// }

    

    /**
     * Auto-fix issues via Claude -> JSON -> Bricks API
     */
    //     public function apply_auto_fix(WP_REST_Request $request) {
    //     $issue = $request->get_json_params();

    //     $prompt = "You are an accessibility assistant. 
    //     The site uses Bricks Builder and AutomaticCSS.
    //     Respond ONLY with structured JSON describing changes:
    //     [
    //     { \"elementId\": \"brxe-123\", \"setting\": \"ariaLabel\", \"before\": \"\", \"after\": \"Main Navigation\" }
    //     ]
    //     Issue: " . json_encode($issue, JSON_PRETTY_PRINT);

    //     $json = $this->call_claude_api($prompt, true);

    //     // 🔹 Convert JSON string → PHP array
    //     $changes = json_decode($json, true);
    //     if (!is_array($changes)) {
    //         return new WP_REST_Response([
    //             'error'   => true,
    //             'message' => 'Invalid JSON from AI',
    //             'raw'     => $json
    //         ], 500);
    //     }

    //     // 🔹 Apply each change to Bricks content
    //     foreach ($changes as $c) {
    //         $this->apply_bricks_change($c);
    //     }


    //     return new WP_REST_Response([
    //         'success'   => true,
    //         'changes'   => $changes,
    //         'changelog' => $this->generate_changelog($changes)
    //     ]);
    // }

    /**
     * Actually update Bricks element data.
     */
    // private function apply_bricks_change($change) {
    //     $element_id = $change['elementId'];
    //     $setting    = $change['setting'];
    //     $after      = $change['after'];

    //     // 1. Load Bricks content (JSON) for the post
    //     $post_id = 123; // TODO: identify from $change or request
    //     $content = get_post_meta($post_id, BRICKS_DB_PAGE_CONTENT, true);
    //     $data    = json_decode($content, true);

    //     if (!isset($data['elements'][$element_id])) return false;

    //     // 2. Apply modification
    //     $data['elements'][$element_id][$setting] = $after;

    //     // 3. Save back into Bricks content
    //     update_post_meta($post_id, BRICKS_DB_PAGE_CONTENT, wp_json_encode($data));

    //     return true;
    // }


    /**
     * Save applied fixes (revision + changelog entry)
     */
    // public function save_fix(WP_REST_Request $request) {
    //     $issue = $request->get_json_params();

    //     // TODO: integrate with Bricks revision system
    //     // Example: create a post meta entry for audit trail
    //     $log = [
    //         'user' => get_current_user_id(),
    //         'time' => current_time('mysql'),
    //         'issue' => $issue
    //     ];
    //     add_option('aa_fix_log_' . time(), $log);

    //     return new WP_REST_Response([
    //         'success' => true,
    //         'message' => 'Fix saved & revision logged'
    //     ]);
    // }



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




    /**
     * Helper: Build changelog entries
     */
    // private function generate_changelog($changes) {
    //     $log = [];
    //     if (!is_array($changes)) return $log;

    //     foreach ($changes as $c) {
    //         $log[] = sprintf(
    //             "Element %s: %s changed from '%s' → '%s'",
    //             $c['elementId'] ?? 'unknown',
    //             $c['setting'] ?? 'setting',
    //             $c['before'] ?? '',
    //             $c['after'] ?? ''
    //         );
    //     }
    //     return $log;
    // }
}
