<?php
/**
 * Integration test runner — executed inside WordPress via WP-CLI eval-file.
 *
 * Run from the wp-whittemore-lab directory:
 *   docker compose run --rm wpcli eval-file \
 *     /var/www/html/wp-content/plugins/accessibility-auditor/tests/integration/run.php \
 *     --path=/var/www/html --allow-root
 *
 * Tests are intentionally free of Claude API calls so they run offline.
 * Each suite creates its own page and deletes it in teardown.
 */

namespace Accessibility_Auditor;

require_once __DIR__ . '/../TestRunner.php';

$t = new \TestRunner();

echo "\033[1m╔══════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1m║     Accessibility Auditor — Integration Tests            ║\033[0m\n";
echo "\033[1m╚══════════════════════════════════════════════════════════╝\033[0m\n";

// ============================================================
// Helpers
// ============================================================

/** Create a scratch page and return its ID. Deleted in teardown. */
function aa_test_create_page( string $slug ): int {
    $existing = get_page_by_path( $slug, OBJECT, 'page' );
    if ( $existing ) {
        wp_delete_post( $existing->ID, true );
    }
    $id = wp_insert_post( [
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => "AA Test: {$slug}",
        'post_name'   => $slug,
        'post_content'=> '',
    ] );
    if ( is_wp_error( $id ) ) {
        \WP_CLI::error( 'aa_test_create_page failed: ' . $id->get_error_message() );
    }
    return (int) $id;
}

function aa_test_bricks_tree(): array {
    return [
        [
            'id'       => 'img_aaa1',
            'name'     => 'image',
            'settings' => [ 'src' => 'photo.jpg' ],
            'children' => [],
        ],
        [
            'id'       => 'link_bbb2',
            'name'     => 'button',
            'settings' => [ 'text' => '<a href="#">Click</a>' ],
            'children' => [],
        ],
    ];
}

/** Mock axe violations array (2 rule groups, 2 issue instances = score 90). */
function aa_test_axe_violations(): array {
    return [
        'violations' => [
            [
                'id'    => 'image-alt',
                'help'  => 'Images must have alternate text',
                'impact'=> 'critical',
                'nodes' => [
                    [ 'target' => [ '#brxe-img_aaa1' ], 'html' => '<img src="photo.jpg">' ],
                ],
            ],
            [
                'id'    => 'link-name',
                'help'  => 'Links must have discernible text',
                'impact'=> 'serious',
                'nodes' => [
                    [ 'target' => [ '#brxe-link_bbb2' ], 'html' => '<a href="#"></a>' ],
                ],
            ],
        ],
        'passes'    => [],
        'incomplete'=> [],
    ];
}

/**
 * Decode revision snapshot payload from post meta.
 *
 * Supports current JSON-string storage and future/legacy array-like storage.
 */
function aa_test_decode_snapshot_payload( $raw ): array {
    if ( is_array( $raw ) ) {
        return $raw;
    }

    if ( ! is_string( $raw ) || $raw === '' ) {
        return [];
    }

    $decoded = json_decode( $raw, true );
    if ( is_array( $decoded ) ) {
        return $decoded;
    }

    $maybe = maybe_unserialize( $raw );
    return is_array( $maybe ) ? $maybe : [];
}

// ============================================================
// Suite 1: ScanManager — DB round-trip & scoring
// ============================================================

$t->suite( 'ScanManager — DB round-trip' );

$page_id = aa_test_create_page( 'aa-int-test-scan' );

$axe = aa_test_axe_violations();
$scan_id = ScanManager::save_scan( $page_id, $axe );

$t->assert_true( $scan_id > 0, 'save_scan returns positive scan_id' );

// Verify meta keys were written.
$stored_score   = (float) get_post_meta( $page_id, '_aa_scan_score', true );
$stored_status  = get_post_meta( $page_id, '_aa_scan_status', true );
$stored_summary = get_post_meta( $page_id, '_aa_scan_summary', true );
$stored_scan_id = (int) get_post_meta( $page_id, '_aa_last_scan_id', true );

$t->assert_equals( $scan_id, $stored_scan_id, '_aa_last_scan_id meta written correctly' );
$t->assert_true( is_string( $stored_status ) && $stored_status !== '', '_aa_scan_status meta is non-empty string' );
$t->assert_true( is_string( $stored_summary ) && $stored_summary !== '', '_aa_scan_summary meta is non-empty string' );

// Score formula: 100 - (issue instances * 5) = 100 - (2 * 5) = 90.
$t->assert_equals( 90.0, $stored_score, 'score = 100 - (issue instances * 5)' );

// Status: 2 violations (each has 1 node) = 2 errors = 'minor' (>0 and ≤5).
$t->assert_equals( 'minor', $stored_status, 'status is "minor" for 2 violation nodes' );

// Summary contains the node counts (2 errors, 0 warnings).
$t->assert_contains( '2 errors', $stored_summary, 'summary mentions error count' );

// Verify DB row exists.
global $wpdb;
$row = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}acss_scans WHERE scan_id = %d",
    $scan_id
) );
$t->assert_not_null( $row, 'DB row exists for scan_id' );
$t->assert_equals( (int) $page_id, (int) $row->post_id, 'DB row has correct post_id' );
$t->assert_equals( 90.0, (float) $row->score, 'DB row has correct score' );

wp_delete_post( $page_id, true );

// ============================================================
// Suite 2: ScanManager — scoring edge cases
// ============================================================

$t->suite( 'ScanManager — scoring edge cases' );

$page_id2 = aa_test_create_page( 'aa-int-test-score' );

// Zero violations → score 100, status 'ok'.
ScanManager::save_scan( $page_id2, [ 'violations' => [], 'passes' => [], 'incomplete' => [] ] );
$t->assert_equals( 100.0, (float) get_post_meta( $page_id2, '_aa_scan_score', true ), 'no violations → score 100' );
$t->assert_equals( 'ok', get_post_meta( $page_id2, '_aa_scan_status', true ), 'no violations → status ok' );

// 20 issue instances → score clamped to 0.
$many_violations = [];
for ( $i = 0; $i < 20; $i++ ) {
    $many_violations[] = [
        'id'    => "rule-{$i}",
        'help'  => "Issue {$i}",
        'nodes' => [
            [ 'target' => [ "#brxe-{$i}" ] ],
        ],
    ];
}
ScanManager::save_scan( $page_id2, [ 'violations' => $many_violations, 'passes' => [], 'incomplete' => [] ] );
$t->assert_equals( 0.0, (float) get_post_meta( $page_id2, '_aa_scan_score', true ), '20 issue instances → score clamped to 0' );
$t->assert_equals( 'major', get_post_meta( $page_id2, '_aa_scan_status', true ), '20 issue instances → status major' );

// A single rule with multiple nodes should deduct per node, not per rule group.
$multi_node = [
    'violations' => [
        [
            'id'    => 'image-alt',
            'help'  => 'Images must have alternate text',
            'nodes' => [
                [ 'target' => [ '#brxe-a' ] ],
                [ 'target' => [ '#brxe-b' ] ],
                [ 'target' => [ '#brxe-c' ] ],
            ],
        ],
    ],
    'passes'     => [],
    'incomplete' => [],
];
ScanManager::save_scan( $page_id2, $multi_node );
$t->assert_equals( 85.0, (float) get_post_meta( $page_id2, '_aa_scan_score', true ), '3 nodes in one violation group → score 85' );

wp_delete_post( $page_id2, true );

// ============================================================
// Suite 3: Revisions — snapshot round-trip
// ============================================================

$t->suite( 'Revisions — snapshot round-trip' );

$page_id3 = aa_test_create_page( 'aa-int-test-revisions' );
$elements  = aa_test_bricks_tree();

$meta_key = Revisions::save_bricks_snapshot( $page_id3, $elements, 'pre_fix_backup' );

$t->assert_true( str_starts_with( $meta_key, 'bricks_revision_pre_fix_backup_' ), 'meta key starts with expected prefix' );

$stored_raw = get_post_meta( $page_id3, $meta_key, true );
$t->assert_true( is_string( $stored_raw ) && strlen( $stored_raw ) > 10, 'snapshot stored as non-empty JSON string' );

$snapshot = aa_test_decode_snapshot_payload( $stored_raw );
$t->assert_equals( 'pre_fix_backup', $snapshot['context'] ?? null, 'snapshot context matches' );
$t->assert_equals( $elements, $snapshot['elements'] ?? null, 'snapshot elements match original tree' );
$t->assert_true( isset( $snapshot['timestamp'] ), 'snapshot has timestamp' );

// Multiple snapshots don't overwrite each other (different time-based keys).
sleep( 1 ); // ensure different unix timestamp
$meta_key2 = Revisions::save_bricks_snapshot( $page_id3, $elements, 'pre_fix_backup' );
$t->assert_true( $meta_key !== $meta_key2, 'second snapshot gets distinct meta key' );

wp_delete_post( $page_id3, true );

// ============================================================
// Suite 4: BricksPatchApplier — apply patch to live post meta
// ============================================================

$t->suite( 'BricksPatchApplier — applied to Bricks post meta' );

$page_id4 = aa_test_create_page( 'aa-int-test-applier' );
$elements4 = aa_test_bricks_tree();
update_post_meta( $page_id4, 'bricks_data', $elements4 );

// Read back and apply a patch.
$stored_elements = get_post_meta( $page_id4, 'bricks_data', true );
$t->assert_true( is_array( $stored_elements ), 'bricks_data meta stored as array' );

$target = BricksElementFinder::find( $stored_elements, 'img_aaa1' );
$t->assert_not_null( $target, 'BricksElementFinder::find locates element in stored meta' );

$patched = BricksPatchApplier::apply( $target, [
    'element_id' => 'img_aaa1',
    'changes'    => [ 'settings' => [ 'alt' => 'Integration test image' ] ],
] );

BricksElementFinder::update( $stored_elements, 'img_aaa1', $patched );
update_post_meta( $page_id4, 'bricks_data', $stored_elements );

// Verify persisted.
$final_elements = get_post_meta( $page_id4, 'bricks_data', true );
$final_el       = BricksElementFinder::find( $final_elements, 'img_aaa1' );
$t->assert_equals( 'Integration test image', $final_el['settings']['alt'] ?? null, 'patched alt persisted to post meta' );
$t->assert_equals( 'photo.jpg', $final_el['settings']['src'] ?? null, 'un-patched setting preserved' );

// Snapshot → patch → restore round-trip.
$snap_key = Revisions::save_bricks_snapshot( $page_id4, $elements4, 'before_patch' );

// Apply destructive patch.
$stored_v2 = get_post_meta( $page_id4, 'bricks_data', true );
$el_v2     = BricksElementFinder::find( $stored_v2, 'link_bbb2' );
$patched_v2 = BricksPatchApplier::apply( $el_v2, [
    'element_id' => 'link_bbb2',
    'changes'    => [ 'settings' => [ 'text' => '<a href="#">Fixed Link</a>' ] ],
] );
BricksElementFinder::update( $stored_v2, 'link_bbb2', $patched_v2 );
update_post_meta( $page_id4, 'bricks_data', $stored_v2 );

// Restore from snapshot.
$snap_raw    = get_post_meta( $page_id4, $snap_key, true );
$snap_data   = aa_test_decode_snapshot_payload( $snap_raw );
$restored    = is_array( $snap_data['elements'] ?? null ) ? $snap_data['elements'] : [];
update_post_meta( $page_id4, 'bricks_data', $restored );

$after_restore = get_post_meta( $page_id4, 'bricks_data', true );
$after_restore = is_array( $after_restore ) ? $after_restore : [];
$restored_link = BricksElementFinder::find( $after_restore, 'link_bbb2' );
$t->assert_equals( '<a href="#">Click</a>', $restored_link['settings']['text'] ?? null, 'snapshot restore reverts to original text' );

wp_delete_post( $page_id4, true );

// ============================================================
// Suite 5: REST endpoint — authentication & validation
// ============================================================

$t->suite( 'REST endpoints — auth & input validation' );

// Unauthenticated request to auto-fix must return 401.
$request = new \WP_REST_Request( 'POST', '/aa/v1/auto-fix' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body( wp_json_encode( [ 'post_id' => 1, 'issue' => [ 'id' => 'image-alt' ] ] ) );

$response = rest_do_request( $request );
$t->assert_equals( 401, $response->get_status(), 'unauthenticated auto-fix returns 401' );

// Authenticated as admin: missing required params should return 4xx.
wp_set_current_user( 1 ); // admin user

$request_bad = new \WP_REST_Request( 'POST', '/aa/v1/auto-fix' );
$request_bad->set_header( 'Content-Type', 'application/json' );
$request_bad->set_body( '{}' ); // empty body
$response_bad = rest_do_request( $request_bad );
// Should not be 2xx — missing required fields or no Bricks data.
$t->assert_true( $response_bad->get_status() >= 400, 'auto-fix with empty body returns 4xx' );

// Guided fix unauthenticated.
$request_g = new \WP_REST_Request( 'POST', '/aa/v1/guided-fix' );
$request_g->set_header( 'Content-Type', 'application/json' );
$request_g->set_body( wp_json_encode( [ 'issue' => [ 'id' => 'image-alt' ] ] ) );
wp_set_current_user( 0 ); // log out
$response_g = rest_do_request( $request_g );
$t->assert_equals( 401, $response_g->get_status(), 'unauthenticated guided-fix returns 401' );

wp_set_current_user( 0 );

// ============================================================
// Suite 6: Revisions::generate_changelog
// ============================================================

$t->suite( 'Revisions::generate_changelog' );

$applied = [
    [
        'id'      => 'el1',
        'changes' => [ 'settings' => [ 'alt' => 'New alt', 'src' => 'new.jpg' ] ],
    ],
    [
        'id'      => 'el2',
        'changes' => [],
    ],
];

$log = Revisions::generate_changelog( $applied );
$t->assert_true( count( $log ) >= 2, 'changelog has at least 2 entries' );
$t->assert_contains( 'el1', $log[0] ?? '', 'first entry mentions element el1' );
$t->assert_contains( 'alt', $log[0] ?? '', 'first entry mentions changed key' );

// Element with empty changes gets a generic "patch applied" line.
$last = end( $log );
$t->assert_contains( 'el2', $last, 'last entry mentions el2' );

// ============================================================
// Done
// ============================================================

$all_passed = $t->summary();
if ( ! $all_passed ) {
    \WP_CLI::warning( 'Some integration tests failed — see output above.' );
} else {
    \WP_CLI::success( 'All integration tests passed.' );
}
