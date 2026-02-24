<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

class Pages_Column {
    public static function init() {
        add_filter( 'manage_pages_columns', [ __CLASS__, 'add_column' ] );
        add_action( 'manage_pages_custom_column', [ __CLASS__, 'render_column' ], 10, 2 );
    }

    public static function add_column( $columns ) {
        $columns['aa_a11y'] = __( 'Accessibility', 'accessibility-auditor' );
        return $columns;
    }

    public static function render_column( $column, $post_id ) {
        if ( $column !== 'aa_a11y' ) return;

        $status  = get_post_meta( $post_id, '_aa_scan_status', true ) ?: 'unknown';
        $scan_id = get_post_meta( $post_id, '_aa_last_scan_id', true );

        $labels = [
            'ok' => [ 'color' => '#28a745', 'title' => 'Pass' ],
            'minor' => [ 'color' => '#ffc107', 'title' => 'Minor issues' ],
            'major' => [ 'color' => '#dc3545', 'title' => 'Major issues' ],
            'needs_review' => [ 'color' => '#17a2b8', 'title' => 'Needs Review' ],
            'unknown' => [ 'color' => '#6c757d', 'title' => 'Not scanned' ],
        ];

        $meta = $labels[ $status ] ?? $labels['unknown'];

        $score   = get_post_meta( $post_id, '_aa_scan_score', true );
        $summary = get_post_meta( $post_id, '_aa_scan_summary', true );
        $url     = $scan_id ? admin_url( 'admin.php?page=aa-scan-report&scan_id=' . intval( $scan_id ) ) : admin_url( 'admin.php?page=aa-scan-report' );

        printf(
            '<a href="%s" title="%s" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%s;flex-shrink:0;"></span>
                <span style="color:%s;font-weight:600;">%s</span>
                %s
            </a>',
            esc_url( $url ),
            esc_attr( $summary ?: $meta['title'] ),
            esc_attr( $meta['color'] ),
            esc_attr( $meta['color'] ),
            $score !== '' && $score !== false ? esc_html( (int) $score ) . '%' : esc_html( $meta['title'] ),
            $summary ? '<span style="color:#999;font-size:11px;">' . esc_html( $summary ) . '</span>' : ''
        );
    }
}
