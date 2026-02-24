<?php
/**
 * Unit tests for BricksPatchApplier.
 */

use Accessibility_Auditor\BricksPatchApplier as Applier;

// ============================================================
// Fixture
// ============================================================

function pa_element(): array {
    return [
        'id'       => 'el1',
        'name'     => 'image',
        'settings' => [
            'src'  => 'photo.jpg',
            'link' => [ 'url' => 'https://example.com', 'type' => 'external' ],
        ],
        'children' => [],
    ];
}

// ============================================================
// changes — merge existing values
// ============================================================

$t->suite( 'BricksPatchApplier::apply — changes' );

$el = Applier::apply( pa_element(), [
    'element_id' => 'el1',
    'changes'    => [ 'settings' => [ 'alt' => 'A scenic view' ] ],
] );
$t->assert_equals( 'A scenic view', $el['settings']['alt'] ?? null, 'changes adds new settings key' );
$t->assert_equals( 'photo.jpg', $el['settings']['src'] ?? null, 'unchanged settings key preserved' );

// Deep merge inside nested settings object.
$el2 = Applier::apply( pa_element(), [
    'element_id' => 'el1',
    'changes'    => [ 'settings' => [ 'link' => [ 'type' => 'internal' ] ] ],
] );
$t->assert_equals( 'internal', $el2['settings']['link']['type'] ?? null, 'deep merge updates nested key' );
$t->assert_equals( 'https://example.com', $el2['settings']['link']['url'] ?? null, 'deep merge preserves sibling key' );

// Scalar overwrite.
$el3 = Applier::apply( pa_element(), [
    'element_id' => 'el1',
    'changes'    => [ 'settings' => [ 'src' => 'new.jpg' ] ],
] );
$t->assert_equals( 'new.jpg', $el3['settings']['src'] ?? null, 'changes overwrites scalar value' );

// ============================================================
// added_keys — identical semantics to changes (deep merge)
// ============================================================

$t->suite( 'BricksPatchApplier::apply — added_keys' );

$el4 = Applier::apply( pa_element(), [
    'element_id' => 'el1',
    'added_keys' => [ 'settings' => [ 'attributes' => [ 'aria-label' => 'Photo' ] ] ],
] );
$t->assert_equals( 'Photo', $el4['settings']['attributes']['aria-label'] ?? null, 'added_keys adds new nested key' );

// ============================================================
// removed_keys — unset matching keys
// ============================================================

$t->suite( 'BricksPatchApplier::apply — removed_keys' );

$el5 = Applier::apply( pa_element(), [
    'element_id'   => 'el1',
    'removed_keys' => [ 'settings' => [ 'src' => true ] ],
] );
$t->assert_false( isset( $el5['settings']['src'] ), 'removed_keys with true removes the key' );
$t->assert_true( isset( $el5['settings']['link'] ), 'sibling key not removed' );

// Nested removal.
$el6 = Applier::apply( pa_element(), [
    'element_id'   => 'el1',
    'removed_keys' => [ 'settings' => [ 'link' => [ 'url' => true ] ] ],
] );
$t->assert_false( isset( $el6['settings']['link']['url'] ), 'removed_keys recurses into nested object' );
$t->assert_equals( 'external', $el6['settings']['link']['type'] ?? null, 'sibling in nested object preserved' );

// Removing non-existent key is a no-op.
$el7 = Applier::apply( pa_element(), [
    'element_id'   => 'el1',
    'removed_keys' => [ 'settings' => [ 'ghost_key' => true ] ],
] );
$t->assert_equals( pa_element()['settings'], $el7['settings'], 'removing absent key is a no-op' );

// ============================================================
// Multiple sections in one patch
// ============================================================

$t->suite( 'BricksPatchApplier::apply — combined patch' );

$el8 = Applier::apply( pa_element(), [
    'element_id'   => 'el1',
    'changes'      => [ 'settings' => [ 'src' => 'updated.jpg' ] ],
    'added_keys'   => [ 'settings' => [ 'alt' => 'Updated photo' ] ],
    'removed_keys' => [ 'settings' => [ 'link' => true ] ],
] );
$t->assert_equals( 'updated.jpg', $el8['settings']['src'] ?? null, 'combined: changes applied' );
$t->assert_equals( 'Updated photo', $el8['settings']['alt'] ?? null, 'combined: added_keys applied' );
$t->assert_false( isset( $el8['settings']['link'] ), 'combined: removed_keys applied' );

// ============================================================
// Edge cases
// ============================================================

$t->suite( 'BricksPatchApplier::apply — edge cases' );

// JSON string input is decoded automatically.
$patch_json = json_encode( [
    'element_id' => 'el1',
    'changes'    => [ 'settings' => [ 'alt' => 'from json' ] ],
] );
$el9 = Applier::apply( pa_element(), $patch_json );
$t->assert_equals( 'from json', $el9['settings']['alt'] ?? null, 'JSON string patch is decoded and applied' );

// Children remains array after merge.
$el_with_children = array_merge( pa_element(), [ 'children' => [ [ 'id' => 'child1' ] ] ] );
$el10 = Applier::apply( $el_with_children, [
    'element_id' => 'el1',
    'changes'    => [ 'settings' => [ 'alt' => 'ok' ] ],
] );
$t->assert_true( is_array( $el10['children'] ), 'children is still an array after merge' );
$t->assert_equals( 1, count( $el10['children'] ), 'children count unchanged' );

// Patch with missing element_id still applied (element_id preserved from original element).
$el11 = Applier::apply( pa_element(), [
    'element_id' => 'el1',
    'changes'    => [ 'settings' => [ 'alt' => 'ok' ] ],
] );
$t->assert_equals( 'el1', $el11['id'], 'element id preserved after patch' );

// Invalid patch throws InvalidArgumentException.
$t->assert_throws(
    fn() => Applier::apply( pa_element(), null ),
    \InvalidArgumentException::class,
    'null patch throws InvalidArgumentException'
);

$t->assert_throws(
    fn() => Applier::apply( pa_element(), 'not valid json {{{' ),
    \InvalidArgumentException::class,
    'invalid JSON string throws InvalidArgumentException'
);
