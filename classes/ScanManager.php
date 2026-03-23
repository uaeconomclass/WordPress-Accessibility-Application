<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

class ScanManager {
    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . 'acss_scans';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            scan_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            status VARCHAR(32) NOT NULL,
            score DECIMAL(5,2) DEFAULT NULL,
            findings LONGTEXT,
            summary VARCHAR(255),
            PRIMARY KEY (scan_id),
            INDEX (post_id),
            INDEX (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function save_scan( $post_id, $results ) {
        global $wpdb;
        $table = $wpdb->prefix . 'acss_scans';

        if ( is_string( $results ) ) {
            $results = json_decode( $results, true );
        }

        $normalized = self::normalize_results( $results );

        $status  = self::compute_status( $normalized );
        $summary = self::summarize_results( $normalized );
        $score   = self::calculate_score( $results );

        $wpdb->insert(
            $table,
            [
                'post_id'    => $post_id,
                'created_at' => current_time( 'mysql' ),
                'status'     => $status,
                'score'      => $score,
                'findings'   => wp_json_encode( $normalized ),
                'summary'    => $summary,
            ],
            [ '%d', '%s', '%s', '%f', '%s', '%s' ]
        );

        $scan_id = $wpdb->insert_id;

       
        update_post_meta( $post_id, '_aa_last_scan_id', $scan_id );
        update_post_meta( $post_id, '_aa_scan_score', $score );
        update_post_meta( $post_id, '_aa_scan_status', $status );
        update_post_meta( $post_id, '_aa_scan_summary', $summary );
        update_post_meta( $post_id, '_aa_scan_results', $results ); // keep full JSON for frontend


        return $scan_id;
    }

    // private static function normalize_results( $axe ) {
    //     $results = [ 'errors' => [], 'warnings' => [], 'passed' => [] ];

    //     if ( isset( $axe['violations'] ) ) {
    //         foreach ( $axe['violations'] as $v ) {
    //             $results['errors'][] = $v['help'] ?? 'Accessibility issue';
    //         }
    //     }
    //     if ( isset( $axe['incomplete'] ) ) {
    //         foreach ( $axe['incomplete'] as $i ) {
    //             $results['warnings'][] = $i['help'] ?? 'Needs manual review';
    //         }
    //     }
    //     if ( isset( $axe['passes'] ) ) {
    //         foreach ( $axe['passes'] as $p ) {
    //             $results['passed'][] = $p['help'] ?? 'Passed check';
    //         }
    //     }

    //     return $results;
    // }


    private static function normalize_results( $axe ) {
        $results = [ 'errors' => [], 'warnings' => [], 'passed' => [] ];

        // Violations = errors
        if ( isset( $axe['violations'] ) ) {
            foreach ( $axe['violations'] as $v ) {
                if (!empty($v['nodes'])) {
                    foreach ($v['nodes'] as $node) {
                        $results['errors'][] = $v['help'] ?? 'Accessibility issue';
                    }
                } else {
                    $results['errors'][] = $v['help'] ?? 'Accessibility issue';
                }
            }
        }

        // Incomplete = warnings (needs review)
        if ( isset( $axe['incomplete'] ) ) {
            foreach ( $axe['incomplete'] as $i ) {
                if (!empty($i['nodes'])) {
                    foreach ($i['nodes'] as $node) {
                        $results['warnings'][] = $i['help'] ?? 'Needs manual review';
                    }
                } else {
                    $results['warnings'][] = $i['help'] ?? 'Needs manual review';
                }
            }
        }

        // Passes = passed checks
        if ( isset( $axe['passes'] ) ) {
            foreach ( $axe['passes'] as $p ) {
                if (!empty($p['nodes'])) {
                    foreach ($p['nodes'] as $node) {
                        $results['passed'][] = $p['help'] ?? 'Passed check';
                    }
                } else {
                    $results['passed'][] = $p['help'] ?? 'Passed check';
                }
            }
        }

        return $results;
    }


    private static function compute_status( $results ) {
        $errors = count( $results['errors'] ?? [] );
        $warnings = count( $results['warnings'] ?? [] );
        if ( $errors > 5 ) return 'major';
        if ( $errors > 0 ) return 'minor';
        if ( $warnings > 0 ) return 'needs_review';
        return 'ok';
    }

    private static function summarize_results( $results ) {
        $err = count( $results['errors'] ?? [] );
        $warn = count( $results['warnings'] ?? [] );
        $pass = count( $results['passed'] ?? [] );
        return sprintf( '%d errors, %d warnings, %d passed', $err, $warn, $pass );
    }

    private static function calculate_score( $results ) {
        // Count each violation instance (node), not just each rule group.
        // Incomplete/manual-review items do not reduce the score.
        $issue_instances = 0;

        foreach ( $results['violations'] ?? [] as $violation ) {
            $nodes = $violation['nodes'] ?? [];
            $issue_instances += ! empty( $nodes ) && is_array( $nodes ) ? count( $nodes ) : 1;
        }

        $score = max( 0, 100 - $issue_instances * 5 );
        return $score;
    }
}
