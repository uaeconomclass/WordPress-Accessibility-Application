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

        $url = $scan_id ? admin_url( 'admin.php?page=aa-scan-report&scan_id=' . intval( $scan_id ) ) : admin_url( 'admin.php?page=aa-scan-report' );

        printf( '<a href="%s" title="%s"><span class="aa-status-dot" style="display:inline-block;width:12px;height:12px;border-radius:50%%;background:%s;"></span></a>',
            esc_url( $url ),
            esc_attr( $meta['title'] ),
            esc_attr( $meta['color'] )
        );
    }
}
