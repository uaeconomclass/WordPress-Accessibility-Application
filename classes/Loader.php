<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Loader - bootstraps plugin modules and assets
 */
class Loader {
    public static function init() {
        require_once AA_PLUGIN_DIR . 'classes/ScanManager.php';
        require_once AA_PLUGIN_DIR . 'classes/Pages_Column.php';
        require_once AA_PLUGIN_DIR . 'classes/Report.php';
        require_once AA_PLUGIN_DIR . 'classes/Revisions.php';
        require_once AA_PLUGIN_DIR . 'classes/Settings.php';
        require_once AA_PLUGIN_DIR . 'classes/SeedFixturesAdmin.php';
        require_once AA_PLUGIN_DIR . 'classes/Admin/Admin.php';
        require_once AA_PLUGIN_DIR . 'classes/BricksElementFinder.php';
        require_once AA_PLUGIN_DIR . 'classes/BricksPatchApplier.php';
        require_once AA_PLUGIN_DIR . 'classes/BricksPatchValidator.php';
        require_once AA_PLUGIN_DIR . 'classes/LlmAuditLogger.php';
        require_once AA_PLUGIN_DIR . 'classes/ClaudeClient.php';
        require_once AA_PLUGIN_DIR . 'classes/AiResponseNormalizer.php';
        require_once AA_PLUGIN_DIR . 'classes/AI.php';
        new \Accessibility_Auditor\AI();



        // Bricks "Save Draft"
        // add_action( 'wp_ajax_bricks_save_post', function() {
        //     if ( empty($_REQUEST['post_id']) ) {
        //         return;
        //     }
        //     $post_id = intval($_REQUEST['post_id']);
        //     update_post_meta( $post_id, '_aa_needs_scan', 1 );
        // }, 20 );

        // // Bricks "Publish/Update"
        // add_action( 'wp_ajax_bricks_publish_post', function() {
        //     if ( empty($_REQUEST['post_id']) ) {
        //         return;
        //     }
        //     $post_id = intval($_REQUEST['post_id']);
        //     update_post_meta( $post_id, '_aa_needs_scan', 1 );
        // }, 20 );

        // // WP core save (covers Classic, Gutenberg, API, etc.)
        // add_action( 'save_post', function( $post_id, $post, $update ) {
        //     if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        //     if ( wp_is_post_revision( $post_id ) ) return;
        //     if ( get_post_type( $post_id ) !== 'page' ) return;

        //     update_post_meta( $post_id, '_aa_needs_scan', 1 );
        // }, 20, 3 );

        



        



        // Hooks
        // Bricks can render UI in admin context while preview runs on frontend.
        // Enqueue on both so the dashboard script is available in the top editor window.
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] ); 
        //add_action( 'wp_footer', [ __CLASS__, 'inject_dashboard_component' ] );

        // AJAX
        add_action( 'wp_ajax_run_scan', [ __CLASS__, 'save_scan_results' ] );
        add_action( 'wp_ajax_aa_debug_log', [ __CLASS__, 'debug_log_event' ] );
        //add_action( 'wp_ajax_aa_guided_fix', [__CLASS__, 'ajax_guided_fix' ] );

        // add_action( 'wp_ajax_guided_fix', [ __CLASS__, 'ajax_guided_fix' ] );
        // add_action( 'wp_ajax_auto_fix', [ __CLASS__, 'ajax_auto_fix' ] );
        // add_action( 'wp_ajax_aa_get_autofix_history', [ __CLASS__, 'ajax_get_autofix_history' ] );

        // Pages column and report
        Pages_Column::init();
        Report::init();

        // settings

        Settings::init();
        SeedFixturesAdmin::init();
        Admin::init(); // 👈 Add this line to register the dashboard widget


        // DB install on activation
        register_activation_hook( AA_PLUGIN_DIR . 'accessibility-auditor.php', [ '\\Accessibility_Auditor\\ScanManager', 'install' ] );
    }

    public static function enqueue_assets() {
        $is_bricks_frontend = isset( $_GET['bricks'] ) && $_GET['bricks'] === 'run';
        $is_bricks_admin    = isset( $_GET['action'] ) && $_GET['action'] === 'bricks';

        if ( ! $is_bricks_frontend && ! $is_bricks_admin ) {
            return;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

            wp_enqueue_script( 'axe-core', AA_PLUGIN_URL . 'assets/js/axe.min.js', [], '4.10.0', true );
            wp_enqueue_script( 'aa-editor-wc', AA_PLUGIN_URL . 'assets/js/editor-wc.js', [ 'axe-core' ], '0.2.4', true );
            wp_enqueue_style( 'aa-editor', AA_PLUGIN_URL . 'assets/css/editor.css', [], '0.1.0' );

            $post_id   = get_the_ID();
            if ( ! $post_id && isset( $_GET['post'] ) ) {
                $post_id = absint( $_GET['post'] );
            }
            $results   = get_post_meta( $post_id, '_aa_scan_results', true );
            $status    = get_post_meta( $post_id, '_aa_scan_status', true );
            $summary   = get_post_meta( $post_id, '_aa_scan_summary', true );
            $score     = get_post_meta( $post_id, '_aa_scan_score', true ); // <-- add this in save_scan()

            $opts = get_option( Settings::OPTION_KEY, [] );

            wp_localize_script( 'aa-editor-wc', 'aaEditor', [
                'nonce'          => wp_create_nonce( 'aa_scan_nonce' ),
                'ajaxurl'        => admin_url( 'admin-ajax.php' ),
                'needsScan'      => 1, //( get_post_meta( $post_id, '_aa_needs_scan', true ) === '1' ) ? 1 : 0,
                'root'           => esc_url_raw( rest_url('aa/v1/') ),
                'restNonce'      => wp_create_nonce('wp_rest'),
                'postId'         => $post_id,
                'results'        => $results,
                'status'         => $status,
                'summary'        => $summary,
                'score'          => $score,
                'wcagLevel'      => $opts['compliance_level'] ?? 'AA',
                'pageBricksIds'  => self::get_page_bricks_element_ids( $post_id ),
            ] );
    }

    public static function inject_dashboard_component() {
        if ( isset($_GET['bricks']) && $_GET['bricks'] === 'run' ) {
            echo '<aa-dashboard></aa-dashboard>';
        }
    }

    public static function save_scan_results() {
        check_ajax_referer( 'aa_scan_nonce', 'nonce' );

        $post_id = intval( $_POST['postId'] ?? 0 );
        $trace_id = sanitize_text_field( wp_unslash( $_POST['traceId'] ?? '' ) );
        $raw     = wp_unslash( $_POST['results'] ?? '' );
        $results = json_decode( $raw, true );

        self::aa_log( 'run_scan.request', [
            'trace_id' => $trace_id,
            'post_id'  => $post_id,
            'raw_len'  => strlen( $raw ),
            'json_ok'  => is_array( $results ),
        ] );

        if ( ! $post_id || ! is_array( $results ) ) {
            self::aa_log( 'run_scan.invalid', [ 'trace_id' => $trace_id, 'post_id' => $post_id ] );
            wp_send_json_error( [ 'message' => 'Invalid request' ], 400 );
        }

        $scan_id = ScanManager::save_scan( $post_id, $results );

        // delete flag
        delete_post_meta( $post_id, '_aa_needs_scan' );

        $response = [
            'scan_id' => $scan_id,
            'status'  => get_post_meta( $post_id, '_aa_scan_status', true ),
            'summary' => get_post_meta( $post_id, '_aa_scan_summary', true ),
            'score'   => get_post_meta( $post_id, '_aa_scan_score', true ),
            'trace_id'=> $trace_id,
        ];

        self::aa_log( 'run_scan.success', [
            'trace_id'   => $trace_id,
            'post_id'    => $post_id,
            'scan_id'    => $scan_id,
            'violations' => count( $results['violations'] ?? [] ),
            'incomplete' => count( $results['incomplete'] ?? [] ),
            'status'     => $response['status'],
            'score'      => $response['score'],
        ] );

        wp_send_json_success( $response );
    }

    public static function debug_log_event() {
        check_ajax_referer( 'aa_scan_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
        }

        $trace_id = sanitize_text_field( wp_unslash( $_POST['traceId'] ?? '' ) );
        $event    = sanitize_text_field( wp_unslash( $_POST['event'] ?? '' ) );
        $post_id  = intval( $_POST['postId'] ?? 0 );
        $data_raw = wp_unslash( $_POST['data'] ?? '' );
        $data     = json_decode( $data_raw, true );

        self::aa_log( 'frontend.' . ( $event ?: 'unknown' ), [
            'trace_id' => $trace_id,
            'post_id'  => $post_id,
            'data'     => is_array( $data ) ? $data : [ 'raw' => substr( (string) $data_raw, 0, 500 ) ],
        ] );

        wp_send_json_success( [ 'ok' => true, 'trace_id' => $trace_id ] );
    }

    public static function aa_log( string $event, array $context = [] ): void {
        $line = sprintf(
            "[%s] %s %s\n",
            gmdate( 'Y-m-d\\TH:i:s\\Z' ),
            $event,
            wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        );

        @file_put_contents( WP_CONTENT_DIR . '/aa-debug.log', $line, FILE_APPEND );
    }

    /**
     * Collect Bricks element IDs that belong to the current post's editable page content.
     *
     * These IDs are used client-side to exclude template/global/header/footer nodes from
     * page-level scan results, score, and auto-fix affordances.
     *
     * @param int $post_id Current page/post ID.
     * @return array<string>
     */
    private static function get_page_bricks_element_ids( int $post_id ): array {
        if ( ! $post_id ) {
            return [];
        }

        $content_key_candidates = [];
        if ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && is_string( BRICKS_DB_PAGE_CONTENT ) && BRICKS_DB_PAGE_CONTENT !== '' ) {
            $content_key_candidates[] = BRICKS_DB_PAGE_CONTENT;
        }
        $content_key_candidates[] = 'bricks_data';
        $content_key_candidates = array_values( array_unique( $content_key_candidates ) );

        $elements = null;
        foreach ( $content_key_candidates as $candidate_key ) {
            $candidate_content  = get_post_meta( $post_id, $candidate_key, true );
            $candidate_elements = is_array( $candidate_content ) ? $candidate_content : json_decode( $candidate_content, true );
            if ( ! empty( $candidate_elements ) && is_array( $candidate_elements ) ) {
                $elements = $candidate_elements;
                break;
            }
        }

        if ( empty( $elements ) || ! is_array( $elements ) ) {
            return [];
        }

        $ids = [];
        $walk = static function( array $nodes ) use ( &$walk, &$ids ): void {
            foreach ( $nodes as $node ) {
                if ( ! empty( $node['id'] ) && is_string( $node['id'] ) ) {
                    $ids[] = $node['id'];
                }
                if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
                    $walk( $node['children'] );
                }
            }
        };

        $walk( $elements );
        return array_values( array_unique( $ids ) );
    }

    /**
     * AJAX endpoint: aa_guided_fix
     * Expect POST:
     *  - nonce
     *  - postId
     *  - issue (JSON string)  (the single issue object from axe results)
     */
    // public static function ajax_guided_fix() {

    //     // nonce + capability
    //     if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'aa_scan_nonce' ) ) {
    //         wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
    //     }
    //     if ( ! current_user_can( 'edit_posts' ) ) {
    //         wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    //     }

    //     $post_id = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
    //     if ( ! $post_id ) {
    //         wp_send_json_error( [ 'message' => 'Missing postId' ], 400 );
    //     }

    //     $raw_issue = isset( $_POST['issue'] ) ? wp_unslash( $_POST['issue'] ) : '';
    //     if ( empty( $raw_issue ) ) {
    //         wp_send_json_error( [ 'message' => 'Missing issue data' ], 400 );
    //     }

    //     $issue = json_decode( $raw_issue, true );
    //     if ( null === $issue || ! is_array( $issue ) ) {
    //         wp_send_json_error( [ 'message' => 'Invalid issue JSON' ], 400 );
    //     }

    //     $ai = new \Accessibility_Auditor\AI();
    //     $result = $ai->guided_fix( $post_id, $issue );

    //     if ( is_wp_error( $result ) ) {
    //         wp_send_json_error( [ 'message' => $result->get_error_message() ], 500 );
    //     }

    //     // $result is array( 'steps' => [ ... ] )
    //     wp_send_json_success( $result );
    // }


    // public static function ajax_auto_fix() {
    //     wp_send_json_success( [ 'fixed' => [ 'Added alt to 1 image' ], 'skipped' => [ 'Contrast requires manual' ] ] );
    // }

    // public static function ajax_get_autofix_history() {
    //     $post_id = intval( $_POST['post_id'] ?? 0 );
    //     $history = Revisions::get_history( $post_id );
    //     wp_send_json_success( $history );
    // }
}
