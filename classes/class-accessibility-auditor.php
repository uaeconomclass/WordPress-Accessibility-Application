<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Accessibility_Auditor {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer', [$this, 'inject_dashboard_component']);

        add_action('wp_ajax_run_scan', [$this, 'save_scan_results']);
    }

    public function enqueue_assets() {
        $url = plugin_dir_url(__FILE__) . '../assets/';

        wp_enqueue_script('axe-core', $url . 'js/axe.min.js', [], '4.8.2', true);
        wp_enqueue_script('aa-editor-wc', $url . 'js/editor-wc.js', ['axe-core'], '0.1', true);
        wp_enqueue_style('aa-editor-css', $url . 'css/editor.css', [], '0.1');
        wp_enqueue_style('aa-admin-css', $url . 'css/admin.css', [], '0.1');

        wp_localize_script('aa-editor-wc', 'aaEditor', [
            'nonce'    => wp_create_nonce('aa_scan_nonce'),
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'postId'   => get_the_ID(),
        ]);
    }

    public function inject_dashboard_component() {
        echo '<aa-dashboard></aa-dashboard>';
    }

    public function save_scan_results() {
        check_ajax_referer('aa_scan_nonce', 'nonce');
        $post_id = intval($_POST['postId'] ?? 0);
        $results = wp_unslash($_POST['results'] ?? '');

        if (!$post_id || !$results) {
            wp_send_json_error(['message' => 'Invalid post ID or results']);
        }

        update_post_meta($post_id, '_aa_scan_results', $results);
        wp_send_json_success(['message' => 'Results saved']);
    }
}

new Accessibility_Auditor();
