<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

class Report {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
    }

    public static function register_page() {
        add_submenu_page(
            'edit.php?post_type=page',
            __( 'Accessibility Report', 'accessibility-auditor' ),
            __( 'Accessibility Report', 'accessibility-auditor' ),
            'edit_pages',
            'aa-scan-report',
            [ __CLASS__, 'render_scan_report' ]
        );
    }

    public static function render_scan_report() {
        global $wpdb;

        $scan_id = intval( $_GET['scan_id'] ?? 0 );
        if ( ! $scan_id ) {
            echo '<div class="notice notice-error"><p>No scan selected.</p></div>';
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
}
