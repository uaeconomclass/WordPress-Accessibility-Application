<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LlmCallsAdmin {
    const PAGE_SLUG = 'aa-llm-calls';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
    }

    public static function register_page() {
        add_submenu_page(
            Admin::MENU_SLUG,
            __( 'LLM Calls', 'accessibility-auditor' ),
            __( 'LLM Calls', 'accessibility-auditor' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table   = LlmAuditLogger::table_name();
        $filters = self::current_filters();

        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'LLM Calls', 'accessibility-auditor' ) . '</h1>';
        echo '<p style="max-width:920px;">' . esc_html__( 'Review Claude usage, latency, token counts, cost estimates, and failure patterns for guided and auto-fix flows.', 'accessibility-auditor' ) . '</p>';

        if ( $table_exists !== $table ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'The LLM audit table has not been created yet. Trigger the plugin installer or make one Claude call to initialize logging.', 'accessibility-auditor' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $where   = [ '1=1' ];
        $params  = [];

        if ( $filters['call_mode'] !== '' ) {
            $where[]  = 'call_mode = %s';
            $params[] = $filters['call_mode'];
        }

        if ( $filters['status'] !== '' ) {
            $where[]  = 'status = %s';
            $params[] = $filters['status'];
        }

        if ( $filters['trace_id'] !== '' ) {
            $where[]  = 'trace_id LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $filters['trace_id'] ) . '%';
        }

        $where_sql = implode( ' AND ', $where );

        $summary_query = "SELECT
                COUNT(*) AS total_calls,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_calls,
                SUM(CASE WHEN status != 'success' THEN 1 ELSE 0 END) AS failed_calls,
                SUM(input_tokens) AS input_tokens,
                SUM(output_tokens) AS output_tokens,
                SUM(estimated_cost_usd) AS total_cost,
                AVG(latency_ms) AS avg_latency_ms
             FROM {$table}
             WHERE {$where_sql}";

        if ( ! empty( $params ) ) {
            $summary_query = $wpdb->prepare( $summary_query, $params );
        }

        $summary = $wpdb->get_row( $summary_query, ARRAY_A );

        $query = "SELECT id, trace_id, call_mode, post_id, rule_id, component, model, prompt_version, status, error_code,
                         prompt_chars, response_chars, input_tokens, output_tokens, estimated_cost_usd, latency_ms,
                         prompt_preview, response_preview, started_at, finished_at
                  FROM {$table}
                  WHERE {$where_sql}
                  ORDER BY id DESC
                  LIMIT 100";

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $query, $params );
        }

        $rows = $wpdb->get_results( $query, ARRAY_A );

        self::render_filters( $filters );
        self::render_summary_cards( $summary );

        if ( empty( $rows ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No LLM call records found for the selected filters.', 'accessibility-auditor' ) . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:1200px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'When', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Mode', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Trace / Rule', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Usage', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Latency', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Cost', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Prompt / Response', 'accessibility-auditor' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $row ) {
            $post_label = ! empty( $row['post_id'] ) ? sprintf( '#%d', (int) $row['post_id'] ) : '—';
            $rule_label = $row['rule_id'] !== '' ? $row['rule_id'] : '—';
            $component  = $row['component'] !== '' ? $row['component'] : '—';
            $status_badge = $row['status'] === 'success' ? '#16a34a' : '#dc2626';

            echo '<tr>';
            echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['started_at'] ) ) . '</td>';
            echo '<td><strong>' . esc_html( $row['call_mode'] ) . '</strong><br><span style="color:#50575e;">' . esc_html( $component ) . '</span></td>';
            echo '<td><code>' . esc_html( $row['trace_id'] ) . '</code><br><span style="color:#50575e;">rule: ' . esc_html( $rule_label ) . ' · post: ' . esc_html( $post_label ) . '</span></td>';
            echo '<td><span style="display:inline-block;padding:3px 8px;border-radius:999px;background:' . esc_attr( $status_badge ) . ';color:#fff;">' . esc_html( $row['status'] ) . '</span>';
            if ( ! empty( $row['error_code'] ) ) {
                echo '<br><code>' . esc_html( $row['error_code'] ) . '</code>';
            }
            echo '</td>';
            echo '<td>in: ' . esc_html( (string) (int) $row['input_tokens'] ) . '<br>out: ' . esc_html( (string) (int) $row['output_tokens'] ) . '<br><span style="color:#50575e;">chars: ' . esc_html( (string) (int) $row['prompt_chars'] ) . ' / ' . esc_html( (string) (int) $row['response_chars'] ) . '</span></td>';
            echo '<td>' . esc_html( (string) (int) $row['latency_ms'] ) . ' ms</td>';
            echo '<td>$' . esc_html( number_format( (float) $row['estimated_cost_usd'], 6 ) ) . '</td>';
            echo '<td style="max-width:380px;">';
            echo '<details><summary>' . esc_html__( 'Prompt', 'accessibility-auditor' ) . '</summary><pre style="white-space:pre-wrap;">' . esc_html( (string) $row['prompt_preview'] ) . '</pre></details>';
            echo '<details><summary>' . esc_html__( 'Response', 'accessibility-auditor' ) . '</summary><pre style="white-space:pre-wrap;">' . esc_html( (string) $row['response_preview'] ) . '</pre></details>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function current_filters(): array {
        return [
            'call_mode' => sanitize_text_field( wp_unslash( $_GET['call_mode'] ?? '' ) ),
            'status'    => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
            'trace_id'  => sanitize_text_field( wp_unslash( $_GET['trace_id'] ?? '' ) ),
        ];
    }

    private static function render_filters( array $filters ) {
        echo '<div style="max-width:1200px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin:18px 0;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Filters', 'accessibility-auditor' ) . '</h2>';
        echo '<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';

        self::render_select( 'call_mode', __( 'Mode', 'accessibility-auditor' ), [
            'guided_fix'      => 'guided_fix',
            'guided_fallback' => 'guided_fallback',
            'auto_fix'        => 'auto_fix',
            'smoke_test'      => 'smoke_test',
        ], $filters['call_mode'] );

        self::render_select( 'status', __( 'Status', 'accessibility-auditor' ), [
            'success' => 'success',
            'error'   => 'error',
        ], $filters['status'] );

        echo '<label style="display:flex;flex-direction:column;gap:6px;min-width:240px;">';
        echo '<span>' . esc_html__( 'Trace ID', 'accessibility-auditor' ) . '</span>';
        echo '<input type="text" name="trace_id" value="' . esc_attr( $filters['trace_id'] ) . '">';
        echo '</label>';

        echo '<p style="margin:0;"><button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'accessibility-auditor' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Reset', 'accessibility-auditor' ) . '</a></p>';
        echo '</form>';
        echo '</div>';
    }

    private static function render_select( string $name, string $label, array $options, string $selected ) {
        echo '<label style="display:flex;flex-direction:column;gap:6px;min-width:180px;">';
        echo '<span>' . esc_html( $label ) . '</span>';
        echo '<select name="' . esc_attr( $name ) . '">';
        echo '<option value="">' . esc_html__( 'All', 'accessibility-auditor' ) . '</option>';
        foreach ( $options as $value => $text ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
        }
        echo '</select>';
        echo '</label>';
    }

    private static function render_summary_cards( array $summary ) {
        $cards = [
            __( 'Total Calls', 'accessibility-auditor' ) => (int) ( $summary['total_calls'] ?? 0 ),
            __( 'Successful', 'accessibility-auditor' )  => (int) ( $summary['success_calls'] ?? 0 ),
            __( 'Failed', 'accessibility-auditor' )      => (int) ( $summary['failed_calls'] ?? 0 ),
            __( 'Input Tokens', 'accessibility-auditor' ) => (int) ( $summary['input_tokens'] ?? 0 ),
            __( 'Output Tokens', 'accessibility-auditor' ) => (int) ( $summary['output_tokens'] ?? 0 ),
            __( 'Avg Latency', 'accessibility-auditor' ) => (int) round( (float) ( $summary['avg_latency_ms'] ?? 0 ) ) . ' ms',
            __( 'Estimated Cost', 'accessibility-auditor' ) => '$' . number_format( (float) ( $summary['total_cost'] ?? 0 ), 6 ),
        ];

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;max-width:1200px;margin:18px 0 20px;">';
        foreach ( $cards as $label => $value ) {
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 18px;">';
            echo '<div style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:.04em;">' . esc_html( $label ) . '</div>';
            echo '<div style="font-size:24px;font-weight:700;margin-top:6px;">' . esc_html( (string) $value ) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}
