<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    const MENU_SLUG = 'aa-dashboard';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'registerAdminMenu' ], 5 );
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'addDashboardWidget' ] );
    }

    public static function registerAdminMenu() {
        add_menu_page(
            __( 'Accessibility Auditor', 'accessibility-auditor' ),
            __( 'Accessibility Auditor', 'accessibility-auditor' ),
            'edit_pages',
            self::MENU_SLUG,
            [ __CLASS__, 'renderOverviewPage' ],
            'dashicons-universal-access-alt',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __( 'Overview', 'accessibility-auditor' ),
            __( 'Overview', 'accessibility-auditor' ),
            'edit_pages',
            self::MENU_SLUG,
            [ __CLASS__, 'renderOverviewPage' ]
        );
    }

    public static function addDashboardWidget() {
        wp_add_dashboard_widget(
            'aa_dashboard',
            __( 'Accessibility Auditor Overview', 'accessibility-auditor' ),
            [ __CLASS__, 'renderDashboardWidget' ]
        );
    }

    public static function renderOverviewPage() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Accessibility Auditor', 'accessibility-auditor' ) . '</h1>';
        echo '<p style="max-width:900px;">' . esc_html__( 'Use this workspace as the central home for accessibility status, recent scan outcomes, reports, and plugin configuration.', 'accessibility-auditor' ) . '</p>';
        echo '<div style="max-width:960px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin-top:16px;">';
        self::renderDashboardWidget();
        echo '</div>';
        echo '</div>';
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
        $total_pages   = count( $pages );
        $scanned_pages = 0;
        $latest_scan_id = 0;

        foreach ( $pages as $page_id ) {
            $status = get_post_meta( $page_id, '_aa_scan_status', true );
            $score  = get_post_meta( $page_id, '_aa_scan_score', true );
            $scan_id = (int) get_post_meta( $page_id, '_aa_last_scan_id', true );

            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }

            if ( is_numeric( $score ) ) {
                $scores[] = (int) $score;
                $scanned_pages++;
            }

            if ( $scan_id > $latest_scan_id ) {
                $latest_scan_id = $scan_id;
            }
        }

        $avg_score = ! empty( $scores ) ? round( array_sum( $scores ) / count( $scores ) ) : 0;
        $unscanned = max( 0, $total_pages - $scanned_pages );
        $grade     = self::grade_for_score( $avg_score );
        $grade_color = self::grade_color( $grade );
        $report_url = $latest_scan_id
            ? admin_url( 'admin.php?page=aa-scan-report&scan_id=' . $latest_scan_id )
            : admin_url( 'edit.php?post_type=page' );

        $next_steps = [];
        if ( $unscanned > 0 ) {
            $next_steps[] = sprintf(
                esc_html__( 'Scan %d unscanned page(s) so the site score reflects the full page set.', 'accessibility-auditor' ),
                $unscanned
            );
        }
        if ( $counts['major'] > 0 ) {
            $next_steps[] = sprintf(
                esc_html__( 'Prioritize %d page(s) with major issues first.', 'accessibility-auditor' ),
                $counts['major']
            );
        }
        if ( $counts['minor'] > 0 ) {
            $next_steps[] = sprintf(
                esc_html__( 'Review %d page(s) with minor issues next.', 'accessibility-auditor' ),
                $counts['minor']
            );
        }
        if ( $counts['needs_review'] > 0 ) {
            $next_steps[] = sprintf(
                esc_html__( 'Manually verify %d page(s) that need review.', 'accessibility-auditor' ),
                $counts['needs_review']
            );
        }
        if ( empty( $next_steps ) ) {
            $next_steps[] = esc_html__( 'Keep scanning pages after updates to maintain the current score.', 'accessibility-auditor' );
        }

        echo '<div class="aa-dashboard-widget">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px;">';
        echo '<div>';
        echo '<div style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:.04em;">' . esc_html__( 'Overall Site Score', 'accessibility-auditor' ) . '</div>';
        echo '<div style="display:flex;align-items:baseline;gap:10px;margin-top:4px;">';
        echo '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 12px;border-radius:999px;background:' . esc_attr( $grade_color ) . ';color:#fff;font-weight:700;font-size:18px;">' . esc_html( $grade ) . '</span>';
        echo '<strong style="font-size:26px;line-height:1;">' . esc_html( $avg_score ) . '%</strong>';
        echo '</div>';
        echo '<div style="margin-top:6px;color:#50575e;">' . sprintf( esc_html__( '%1$d of %2$d pages scanned', 'accessibility-auditor' ), $scanned_pages, $total_pages ) . '</div>';
        echo '</div>';
        echo '<div style="text-align:right;color:#50575e;font-size:12px;">';
        echo '<div>' . sprintf( esc_html__( '%d pages OK', 'accessibility-auditor' ), $counts['ok'] ) . '</div>';
        echo '<div>' . sprintf( esc_html__( '%d unscanned', 'accessibility-auditor' ), $unscanned ) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div style="margin:12px 0 10px;">';
        echo '<strong style="display:block;margin-bottom:8px;">' . esc_html__( 'Issue Summary', 'accessibility-auditor' ) . '</strong>';
        echo '<ul style="margin:0;padding-left:18px;">';
        echo '<li><span style="color:#dc3545;">●</span> ' . sprintf( esc_html__( '%d page(s) with major issues', 'accessibility-auditor' ), $counts['major'] ) . '</li>';
        echo '<li><span style="color:#f59e0b;">●</span> ' . sprintf( esc_html__( '%d page(s) with minor issues', 'accessibility-auditor' ), $counts['minor'] ) . '</li>';
        echo '<li><span style="color:#0ea5e9;">●</span> ' . sprintf( esc_html__( '%d page(s) need manual review', 'accessibility-auditor' ), $counts['needs_review'] ) . '</li>';
        echo '<li><span style="color:#16a34a;">●</span> ' . sprintf( esc_html__( '%d page(s) currently pass', 'accessibility-auditor' ), $counts['ok'] ) . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '<div style="margin:12px 0 10px;">';
        echo '<strong style="display:block;margin-bottom:8px;">' . esc_html__( 'Recommended Actions', 'accessibility-auditor' ) . '</strong>';
        echo '<ul style="margin:0;padding-left:18px;">';
        foreach ( $next_steps as $step ) {
            echo '<li>' . esc_html( $step ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';

        echo '<p style="margin:14px 0 0;display:flex;gap:12px;flex-wrap:wrap;">';
        echo '<a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">' . esc_html__( 'View Pages', 'accessibility-auditor' ) . '</a>';
        echo '<a class="button" href="' . esc_url( $report_url ) . '">' . esc_html__( 'Open Latest Report', 'accessibility-auditor' ) . '</a>';
        echo '</p>';
        echo '</div>';
    }

    private static function grade_for_score( int $score ): string {
        if ( $score >= 95 ) {
            return 'A';
        }
        if ( $score >= 85 ) {
            return 'B';
        }
        if ( $score >= 70 ) {
            return 'C';
        }
        if ( $score >= 50 ) {
            return 'D';
        }

        return 'F';
    }

    private static function grade_color( string $grade ): string {
        $colors = [
            'A' => '#166534',
            'B' => '#16a34a',
            'C' => '#ea580c',
            'D' => '#ef4444',
            'F' => '#991b1b',
        ];

        return $colors[ $grade ] ?? '#6b7280';
    }
}
