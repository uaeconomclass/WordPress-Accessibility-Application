<?php
/**
 * Unit tests for BricksElementFinder.
 *
 * Covers: find(), update(), from_issue() — all three fallback levels.
 */

use Accessibility_Auditor\BricksElementFinder as Finder;

// ============================================================
// Fixtures
// ============================================================

function ef_tree(): array {
    return [
        [
            'id'       => 'root1',
            'name'     => 'section',
            'settings' => [ 'tag' => 'section' ],
            'children' => [
                [
                    'id'       => 'img1',
                    'name'     => 'image',
                    'settings' => [ 'src' => 'photo.jpg' ],
                    'children' => [],
                ],
                [
                    'id'       => 'deep_parent',
                    'name'     => 'div',
                    'settings' => [],
                    'children' => [
                        [
                            'id'       => 'deep_child',
                            'name'     => 'text-basic',
                            'settings' => [ 'text' => '<p>Hello world</p>' ],
                            'children' => [],
                        ],
                    ],
                ],
            ],
        ],
        [
            'id'       => 'link1',
            'name'     => 'button',
            'settings' => [ 'text' => '<a href="#">Click me</a>' ],
            'children' => [],
        ],
    ];
}

// ============================================================
// find()
// ============================================================

$t->suite( 'BricksElementFinder::find' );

$t->assert_not_null( Finder::find( ef_tree(), 'root1' ), 'finds top-level element' );
$t->assert_equals( 'root1', Finder::find( ef_tree(), 'root1' )['id'], 'returned element has correct id' );

$t->assert_not_null( Finder::find( ef_tree(), 'img1' ), 'finds first-level child' );
$t->assert_equals( 'image', Finder::find( ef_tree(), 'img1' )['name'], 'child element has correct name' );

$t->assert_not_null( Finder::find( ef_tree(), 'deep_child' ), 'finds deeply nested element' );
$t->assert_equals( 'text-basic', Finder::find( ef_tree(), 'deep_child' )['name'], 'deep element has correct name' );

$t->assert_null( Finder::find( ef_tree(), 'does_not_exist' ), 'returns null for missing id' );
$t->assert_null( Finder::find( [], 'anything' ), 'returns null on empty tree' );

// ============================================================
// update()
// ============================================================

$t->suite( 'BricksElementFinder::update' );

$tree = ef_tree();
$result = Finder::update( $tree, 'img1', [ 'id' => 'img1', 'name' => 'image', 'settings' => [ 'alt' => 'A photo' ] ] );
$t->assert_true( $result, 'update() returns true when element found' );
$updated = Finder::find( $tree, 'img1' );
$t->assert_equals( 'A photo', $updated['settings']['alt'] ?? null, 'settings are updated in-place' );

$tree2 = ef_tree();
$result2 = Finder::update( $tree2, 'deep_child', [ 'id' => 'deep_child', 'name' => 'text-basic', 'settings' => [ 'text' => 'patched' ] ] );
$t->assert_true( $result2, 'update() returns true for nested element' );
$updated2 = Finder::find( $tree2, 'deep_child' );
$t->assert_equals( 'patched', $updated2['settings']['text'] ?? null, 'nested element updated correctly' );

$tree3 = ef_tree();
$result3 = Finder::update( $tree3, 'ghost', [ 'id' => 'ghost' ] );
$t->assert_false( $result3, 'update() returns false when element not found' );

// ============================================================
// from_issue() — Level 1: #brxe-{id} in target selectors
// ============================================================

$t->suite( 'BricksElementFinder::from_issue — Level 1 (target selector)' );

$issue_l1 = [
    'id'    => 'image-alt',
    'nodes' => [
        [
            'target' => [ '#brxe-img1' ],
            'html'   => '<img src="photo.jpg">',
        ],
    ],
];

$found_l1 = Finder::from_issue( ef_tree(), $issue_l1 );
$t->assert_equals( 1, count( $found_l1 ), 'finds one element via target selector' );
$t->assert_equals( 'img1', $found_l1[0]['id'] ?? null, 'correct element found via target selector' );

// Multiple nodes same element — deduplication.
$issue_l1_dup = [
    'id'    => 'image-alt',
    'nodes' => [
        [ 'target' => [ '#brxe-img1' ], 'html' => '<img>' ],
        [ 'target' => [ '#brxe-img1' ], 'html' => '<img>' ],
    ],
];
$found_dup = Finder::from_issue( ef_tree(), $issue_l1_dup );
$t->assert_equals( 1, count( $found_dup ), 'deduplicates repeated element IDs across nodes' );

// ============================================================
// from_issue() — Level 2: id="brxe-{id}" in node.html
// ============================================================

$t->suite( 'BricksElementFinder::from_issue — Level 2 (id attr in html)' );

$issue_l2 = [
    'id'    => 'link-name',
    'nodes' => [
        [
            'target' => [ 'a' ],           // bare tag — no #brxe prefix
            'html'   => '<a id="brxe-link1" href="#"></a>',
        ],
    ],
];

$found_l2 = Finder::from_issue( ef_tree(), $issue_l2 );
$t->assert_equals( 1, count( $found_l2 ), 'finds element via id attr in html' );
$t->assert_equals( 'link1', $found_l2[0]['id'] ?? null, 'correct element found via id attr' );

// ============================================================
// from_issue() — Level 3: content match on settings.text
// ============================================================

$t->suite( 'BricksElementFinder::from_issue — Level 3 (content match)' );

$issue_l3 = [
    'id'    => 'color-contrast',
    'nodes' => [
        [
            'target' => [ 'p' ],
            'html'   => '<p>Hello world</p>',
        ],
    ],
];

$found_l3 = Finder::from_issue( ef_tree(), $issue_l3 );
$t->assert_equals( 1, count( $found_l3 ), 'finds element via content match' );
$t->assert_equals( 'deep_child', $found_l3[0]['id'] ?? null, 'correct element found via content match' );

// No match at all.
$issue_none = [
    'id'    => 'whatever',
    'nodes' => [
        [
            'target' => [ 'span' ],
            'html'   => '<span>Totally unrelated content xyz</span>',
        ],
    ],
];
$found_none = Finder::from_issue( ef_tree(), $issue_none );
$t->assert_equals( 0, count( $found_none ), 'returns empty array when no element matches' );

// Axe-injected style attribute stripped before content compare.
$issue_styled = [
    'id'    => 'color-contrast',
    'nodes' => [
        [
            'target' => [ 'p' ],
            'html'   => '<p style="outline: 3px solid red;">Hello world</p>',
        ],
    ],
];
$found_styled = Finder::from_issue( ef_tree(), $issue_styled );
$t->assert_equals( 1, count( $found_styled ), 'strips axe outline style before content compare' );
