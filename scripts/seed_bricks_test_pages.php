<?php
/**
 * Seed Bricks-based test pages for Accessibility Auditor manual/integration QA.
 *
 * Usage (inside wp-whittemore-lab):
 *   docker compose run --rm wpcli eval-file \
 *     /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php \
 *     --path=/var/www/html --allow-root
 *
 * What it does:
 * - Creates/updates a small set of WordPress pages
 * - Writes Bricks element trees into `bricks_data` post meta
 * - Provides deterministic targets for common axe rule IDs / auto-fix paths
 *
 * Notes:
 * - This script seeds Bricks JSON (`bricks_data`) for testing plugin internals.
 * - Visual rendering depends on the active Bricks version/theme and may vary.
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "ABSPATH not defined. Run via wp-cli.\n";
    return;
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    echo "WordPress functions unavailable. Run via wp-cli with WP loaded.\n";
    return;
}

/**
 * Build a simple Bricks text-basic element.
 */
function aa_seed_text_basic( string $id, string $html ): array {
    return [
        'id'       => $id,
        'name'     => 'text-basic',
        'settings' => [ 'text' => $html ],
        'children' => [],
    ];
}

/**
 * Build a simple Bricks image element.
 */
function aa_seed_image( string $id, string $src ): array {
    return [
        'id'       => $id,
        'name'     => 'image',
        'settings' => [
            'image' => [ 'url' => $src ],
            // altText intentionally omitted for image-alt test cases.
        ],
        'children' => [],
    ];
}

/**
 * Build a page fixture spec list.
 */
function aa_seed_bricks_fixtures(): array {
    return [
        [
            'slug'      => 'aa-fixture-image-alt',
            'title'     => 'AA Fixture - Image Alt',
            'post_html' => '<p>Fixture page for image-alt rule.</p>',
            'bricks'    => [
                aa_seed_image( 'img_alt_01', 'https://via.placeholder.com/240x120' ),
            ],
            'rule_ids'   => [ 'image-alt' ],
            'notes'      => 'Image element without altText for auto-fix/image-alt testing.',
        ],
        [
            'slug'      => 'aa-fixture-input-image-alt',
            'title'     => 'AA Fixture - Input Image Alt',
            'post_html' => '<p>Fixture page for input-image-alt rule.</p>',
            'bricks'    => [
                [
                    'id'       => 'input_img_01',
                    'name'     => 'image',
                    'settings' => [
                        'image' => [ 'url' => 'https://via.placeholder.com/120x50?text=Submit' ],
                    ],
                    'children' => [],
                ],
            ],
            'rule_ids'   => [ 'input-image-alt' ],
            'notes'      => 'Input-image style fixture for altText auto-fix scenarios (settings.altText).',
        ],
        [
            'slug'      => 'aa-fixture-link-name',
            'title'     => 'AA Fixture - Link Name',
            'post_html' => '<p>Fixture page for link-name rule.</p>',
            'bricks'    => [
                aa_seed_text_basic( 'txt_link_01', '<a href="#empty-link"></a>' ),
            ],
            'rule_ids'   => [ 'link-name' ],
            'notes'      => 'Inline empty link inside text-basic to exercise BricksElementFinder HTML fallback.',
        ],
        [
            'slug'      => 'aa-fixture-color-contrast',
            'title'     => 'AA Fixture - Color Contrast',
            'post_html' => '<p>Fixture page for color-contrast rule.</p>',
            'bricks'    => [
                aa_seed_text_basic(
                    'txt_cc_01',
                    '<p style="color:#cfcfcf;background:#ffffff;padding:8px">Low contrast text fixture</p>'
                ),
            ],
            'rule_ids'   => [ 'color-contrast' ],
            'notes'      => 'Text-basic fixture for color-contrast detection and _cssCustom patching.',
        ],
        [
            'slug'      => 'aa-fixture-frame-title',
            'title'     => 'AA Fixture - Frame Title',
            'post_html' => '<p>Fixture page for frame-title rule.</p>',
            'bricks'    => [
                aa_seed_text_basic(
                    'txt_iframe_01',
                    '<iframe src="https://example.com" width="300" height="150"></iframe>'
                ),
            ],
            'rule_ids'   => [ 'frame-title' ],
            'notes'      => 'Inline iframe without title to test frame-title mapping/fixes.',
        ],
        [
            'slug'      => 'aa-fixture-button-name',
            'title'     => 'AA Fixture - Button Name',
            'post_html' => '<p>Fixture page for button-name rule.</p>',
            'bricks'    => [
                aa_seed_text_basic(
                    'txt_btn_01',
                    '<button type="button"></button>'
                ),
            ],
            'rule_ids'  => [ 'button-name' ],
            'notes'     => 'Inline empty button inside text-basic for deterministic button-name detection in Bricks preview.',
        ],
        [
            'slug'      => 'aa-fixture-aria-label',
            'title'     => 'AA Fixture - Aria Label',
            'post_html' => '<p>Fixture page for aria-label rule.</p>',
            'bricks'    => [
                aa_seed_text_basic(
                    'txt_aria_01',
                    '<div role="img" aria-label=""></div>'
                ),
            ],
            'rule_ids'  => [ 'aria-label' ],
            'notes'     => 'Inline element with empty aria-label to trigger axe aria-label rule deterministically.',
        ],
    ];
}

/**
 * Create or update a page and attach Bricks fixture data.
 */
function aa_seed_detect_bricks_content_meta_key(): string {
    if ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && is_string( BRICKS_DB_PAGE_CONTENT ) && BRICKS_DB_PAGE_CONTENT !== '' ) {
        return BRICKS_DB_PAGE_CONTENT;
    }

    // Try to infer from an existing Bricks page on this site (e.g. `_bricks_page_content_2`).
    $bricks_pages = get_posts( [
        'post_type'      => 'any',
        'post_status'    => 'any',
        'posts_per_page' => 20,
        'meta_key'       => '_bricks_editor_mode',
        'meta_value'     => 'bricks',
        'fields'         => 'ids',
    ] );

    foreach ( $bricks_pages as $pid ) {
        $meta = get_post_meta( (int) $pid );
        foreach ( array_keys( $meta ) as $meta_key ) {
            if ( preg_match( '/^_bricks_page_content(_\d+)?$/', (string) $meta_key ) ) {
                return (string) $meta_key;
            }
        }
    }

    // Conservative fallback for this lab setup (observed on current Bricks install).
    return '_bricks_page_content_2';
}

function aa_seed_upsert_fixture_page( array $fixture ): array {
    $existing = get_page_by_path( $fixture['slug'], OBJECT, 'page' );

    $postarr = [
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => $fixture['title'],
        'post_name'    => $fixture['slug'],
        'post_content' => $fixture['post_html'],
    ];

    if ( $existing ) {
        $postarr['ID'] = (int) $existing->ID;
        $post_id = wp_update_post( $postarr, true );
        $action  = 'updated';
    } else {
        $post_id = wp_insert_post( $postarr, true );
        $action  = 'created';
    }

    if ( is_wp_error( $post_id ) ) {
        return [
            'ok'      => false,
            'slug'    => $fixture['slug'],
            'message' => $post_id->get_error_message(),
        ];
    }

    // Mark the page as Bricks-managed content so the builder opens a real canvas.
    update_post_meta( (int) $post_id, '_bricks_editor_mode', 'bricks' );
    update_post_meta( (int) $post_id, '_bricks_template_type', 'content' );

    // Store Bricks element tree in both legacy/test key and the active Bricks content key.
    $bricks_content_key = aa_seed_detect_bricks_content_meta_key();
    update_post_meta( (int) $post_id, $bricks_content_key, $fixture['bricks'] );

    // Bricks uses `bricks_data` in some contexts / test utilities.
    update_post_meta( (int) $post_id, 'bricks_data', $fixture['bricks'] );
    update_post_meta( (int) $post_id, '_aa_fixture_rule_ids', $fixture['rule_ids'] );
    update_post_meta( (int) $post_id, '_aa_fixture_notes', $fixture['notes'] );

    return [
        'ok'        => true,
        'action'    => $action,
        'post_id'   => (int) $post_id,
        'slug'      => $fixture['slug'],
        'title'     => $fixture['title'],
        'rule_ids'  => $fixture['rule_ids'],
        'bricks_ct' => count( $fixture['bricks'] ),
        'bricks_key'=> $bricks_content_key,
    ];
}

$fixtures = aa_seed_bricks_fixtures();
$results  = [];

foreach ( $fixtures as $fixture ) {
    $results[] = aa_seed_upsert_fixture_page( $fixture );
}

foreach ( $results as $row ) {
    if ( ! $row['ok'] ) {
        if ( class_exists( 'WP_CLI' ) ) {
            WP_CLI::warning( sprintf( '[%s] %s', $row['slug'], $row['message'] ) );
        } else {
            echo "[WARN] {$row['slug']}: {$row['message']}\n";
        }
        continue;
    }

    $msg = sprintf(
        '%s #%d %s (%s) - rules: %s - bricks elements: %d',
        strtoupper( $row['action'] ),
        $row['post_id'],
        $row['title'],
        $row['slug'],
        implode( ', ', $row['rule_ids'] ),
        $row['bricks_ct']
    );
    $msg .= sprintf( ' - key: %s', $row['bricks_key'] ?? 'n/a' );

    if ( class_exists( 'WP_CLI' ) ) {
        WP_CLI::success( $msg );
    } else {
        echo "[OK] {$msg}\n";
    }
}

if ( class_exists( 'WP_CLI' ) ) {
    WP_CLI::log( 'Fixtures seeded. Open Pages and test in Bricks editor with Accessibility Auditor.' );
    WP_CLI::log( 'Optional: run tests/integration/run.php via WP-CLI for offline integration checks.' );
}
