<?php
// Seed a dummy scan row for local testing in wp-env.
// Usage:
//   wp eval-file wp-content/plugins/accessibility-auditor/scripts/seed_dummy_scan.php

if ( ! defined( 'ABSPATH' ) ) {
    // In wp-cli context, ABSPATH is defined. If not, bail.
    echo "ABSPATH not defined. Run via wp-cli.\n";
    return;
}

$post_id = 2; // "Sample Page" in a fresh wp-env install.

if ( ! class_exists( '\Accessibility_Auditor\ScanManager' ) ) {
    echo "ScanManager not loaded. Is the plugin active?\n";
    return;
}

$results = [
    'violations' => [],
    'incomplete' => [],
    'passes' => [
        [ 'help' => 'Dummy scan (seeded via wp-cli)' ],
    ],
];

$scan_id = \Accessibility_Auditor\ScanManager::save_scan( $post_id, $results );
echo "Seeded scan_id={$scan_id} for post_id={$post_id}\n";

