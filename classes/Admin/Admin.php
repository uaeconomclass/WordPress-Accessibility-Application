<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public static function init() {
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'addDashboardWidget' ] );
    }

    public static function addDashboardWidget() {
        wp_add_dashboard_widget(
            'aa_dashboard',
            __( 'Accessibility Auditor Overview', 'accessibility-auditor' ),
            [ __CLASS__, 'renderDashboardWidget' ]
        );
    }

    public static function renderDashboardWidget() {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $counts = [
            'major'        => 0,
            'minor'        => 0,
            'needs_review' => 0,
            'ok'           => 0,
        ];
        $scores = [];

        foreach ( $pages as $page_id ) {
            $status = get_post_meta( $page_id, '_aa_scan_status', true );
            $score  = get_post_meta( $page_id, '_aa_scan_score', true );

            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }

            if ( is_numeric( $score ) ) {
                $scores[] = (int) $score;
            }
        }

        $avg_score = ! empty( $scores ) ? round( array_sum( $scores ) / count( $scores ) ) : 0;

        echo '<p><strong>' . esc_html__( 'Average Accessibility Score:', 'accessibility-auditor' ) . '</strong> ' . esc_html( $avg_score ) . '%</p>';

        echo '<ul>';
        echo '<li><span style="color:red;">●</span> ' . sprintf( esc_html__( '%d pages with major issues', 'accessibility-auditor' ), $counts['major'] ) . '</li>';
        echo '<li><span style="color:orange;">●</span> ' . sprintf( esc_html__( '%d pages with minor issues', 'accessibility-auditor' ), $counts['minor'] ) . '</li>';
        echo '<li><span style="color:goldenrod;">●</span> ' . sprintf( esc_html__( '%d pages need review', 'accessibility-auditor' ), $counts['needs_review'] ) . '</li>';
        echo '<li><span style="color:green;">●</span> ' . sprintf( esc_html__( '%d pages OK', 'accessibility-auditor' ), $counts['ok'] ) . '</li>';
        echo '</ul>';

        echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">' . esc_html__( 'View All Pages', 'accessibility-auditor' ) . '</a></p>';
    }
}

Admin::init();
