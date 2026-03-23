<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

class Report {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
    }

    public static function register_page() {
        add_submenu_page(
            \Accessibility_Auditor\Admin::MENU_SLUG,
            __( 'Accessibility Report', 'accessibility-auditor' ),
            __( 'Reports', 'accessibility-auditor' ),
            'edit_pages',
            'aa-scan-report',
            [ __CLASS__, 'render_scan_report' ]
        );
    }

    public static function render_scan_report() {
        global $wpdb;

        $scan_id = intval( $_GET['scan_id'] ?? 0 );
        if ( ! $scan_id ) {
            self::render_report_index();
            return;
        }

        $table = $wpdb->prefix . 'acss_scans';
        $scan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE scan_id = %d", $scan_id ) );

        if ( ! $scan ) {
            echo '<div class="notice notice-error"><p>Scan not found.</p></div>';
            return;
        }

        $findings = json_decode( $scan->findings, true );
        $errors = $findings['errors'] ?? [];
        $warnings = $findings['warnings'] ?? [];
        $passed = $findings['passed'] ?? [];

        $error_count = count( $errors );
        $warning_count = count( $warnings );
        $pass_count = count( $passed );
        $total_issues = $error_count + $warning_count;

        echo '<div class="wrap">';
        echo '<h1>Accessibility Scan Report</h1>';

        if ( $total_issues > 0 ) {
            printf( '<span style="background:#dc3545;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold;">%d Total Issues</span>', $total_issues );
        } else {
            echo '<span style="background:#28a745;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold;">No Issues 🎉</span>';
        }

        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#aa-errors" class="nav-tab nav-tab-active" style="color:#dc3545;">❌ Errors (' . $error_count . ')</a>';
        echo '<a href="#aa-warnings" class="nav-tab" style="color:#ffc107;">⚠️ Warnings (' . $warning_count . ')</a>';
        echo '<a href="#aa-passed" class="nav-tab" style="color:#28a745;">✅ Passed (' . $pass_count . ')</a>';
        echo '</h2>';

        echo '<div id="aa-errors" class="aa-tab-content">';
        if ( $error_count ) {
            echo '<ul>';
            foreach ( $errors as $msg ) {
                printf( '<li style="color:#dc3545;">❌ %s</li>', esc_html( $msg ) );
            }
            echo '</ul>';
        } else {
            echo '<p><em>No errors found.</em></p>';
        }
        echo '</div>';

        echo '<div id="aa-warnings" class="aa-tab-content" style="display:none;">';
        if ( $warning_count ) {
            echo '<ul>';
            foreach ( $warnings as $msg ) {
                printf( '<li style="color:#ffc107;">⚠️ %s</li>', esc_html( $msg ) );
            }
            echo '</ul>';
        } else {
            echo '<p><em>No warnings.</em></p>';
        }
        echo '</div>';

        echo '<div id="aa-passed" class="aa-tab-content" style="display:none;">';
        if ( $pass_count ) {
            echo '<ul>';
            foreach ( $passed as $msg ) {
                printf( '<li style="color:#28a745;">✅ %s</li>', esc_html( $msg ) );
            }
            echo '</ul>';
        } else {
            echo '<p><em>No passing checks.</em></p>';
        }
        echo '</div>';

        echo '</div>';

        ?>
        <script>
        jQuery(document).ready(function($) {
            $(".nav-tab-wrapper a").on("click", function(e) {
                e.preventDefault();
                $(".nav-tab-wrapper a").removeClass("nav-tab-active");
                $(this).addClass("nav-tab-active");

                $(".aa-tab-content").hide();
                $($(this).attr("href")).show();
            });
        });
        </script>
        <?php
    }

    private static function render_report_index() {
        global $wpdb;

        $table = $wpdb->prefix . 'acss_scans';
        $rows  = $wpdb->get_results(
            "SELECT s.scan_id, s.post_id, s.created_at, s.status, s.score, s.summary
             FROM {$table} s
             INNER JOIN (
                SELECT post_id, MAX(scan_id) AS latest_scan_id
                FROM {$table}
                GROUP BY post_id
             ) latest ON latest.latest_scan_id = s.scan_id
             ORDER BY s.created_at DESC
             LIMIT 25"
        );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Accessibility Reports', 'accessibility-auditor' ) . '</h1>';
        echo '<p>' . esc_html__( 'Open the latest scan for any page, or start from the Pages screen to run a fresh scan in Bricks.', 'accessibility-auditor' ) . '</p>';

        if ( empty( $rows ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No scans found yet. Open a page in Bricks and run an accessibility scan to populate reports.', 'accessibility-auditor' ) . '</p></div>';
            echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">' . esc_html__( 'View Pages', 'accessibility-auditor' ) . '</a></p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:1100px;margin-top:16px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Page', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Score', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Summary', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Scanned', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Action', 'accessibility-auditor' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $row ) {
            $post_title = get_the_title( (int) $row->post_id ) ?: sprintf( __( 'Page #%d', 'accessibility-auditor' ), (int) $row->post_id );
            $report_url = admin_url( 'admin.php?page=aa-scan-report&scan_id=' . (int) $row->scan_id );
            $status     = sanitize_text_field( (string) $row->status );
            $score      = is_numeric( $row->score ) ? (int) round( (float) $row->score ) . '%' : '—';

            echo '<tr>';
            echo '<td><strong>' . esc_html( $post_title ) . '</strong></td>';
            echo '<td>' . esc_html( $score ) . '</td>';
            echo '<td>' . esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ) . '</td>';
            echo '<td>' . esc_html( (string) $row->summary ) . '</td>';
            echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row->created_at ) ) . '</td>';
            echo '<td><a class="button button-secondary" href="' . esc_url( $report_url ) . '">' . esc_html__( 'Open Report', 'accessibility-auditor' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p style="margin-top:16px;"><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">' . esc_html__( 'View Pages', 'accessibility-auditor' ) . '</a></p>';
        echo '</div>';
    }
}
