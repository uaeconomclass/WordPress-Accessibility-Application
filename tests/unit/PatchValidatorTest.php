<?php
/**
 * Unit tests for BricksPatchValidator.
 */

use Accessibility_Auditor\BricksPatchValidator as Validator;

// ============================================================
// Helpers
// ============================================================

function minimal_patch( string $id = 'abc123' ): array {
    return [
        'element_id' => $id,
        'changes'    => [ 'settings' => [ 'alt' => 'A description' ] ],
    ];
}

// ============================================================
// Happy-path
// ============================================================

$t->suite( 'BricksPatchValidator — valid patches' );

$t->assert_null(
    Validator::validate( minimal_patch(), 'abc123' ),
    'valid minimal patch passes'
);

$t->assert_null(
    Validator::validate(
        [
            'element_id'   => 'el1',
            'changes'      => [ 'settings' => [ 'tag' => 'button', 'aria-label' => 'Submit' ] ],
            'added_keys'   => [ 'settings' => [ 'attributes' => [ 'aria-hidden' => 'false' ] ] ],
            'removed_keys' => [ 'settings' => [ '_cssCustom' => true ] ],
        ],
        'el1'
    ),
    'patch with all three sections passes'
);

$t->assert_null(
    Validator::validate(
        [
            'element_id' => 'el2',
            'changes'    => [ 'settings' => [ '_cssCustom' => '#brxe-el2 { color: red; }' ] ],
        ],
        'el2'
    ),
    '_cssCustom with correct selector passes'
);

$t->assert_null(
    Validator::validate(
        [
            'element_id' => 'el3',
            'added_keys' => [
                'settings' => [
                    'attributes' => [
                        'aria-label'    => 'Logo',
                        'role'          => 'img',
                        'title'         => 'Site logo',
                        'tabindex'      => '0',
                        'alt'           => 'Logo',
                        'lang'          => 'en',
                        'data-tooltip'  => 'info',
                    ],
                ],
            ],
        ],
        'el3'
    ),
    'all allowed attribute names pass'
);

// ============================================================
// element_id failures
// ============================================================

$t->suite( 'BricksPatchValidator — element_id checks' );

$t->assert_not_null(
    Validator::validate( [ 'changes' => [ 'settings' => [ 'alt' => 'x' ] ] ], 'abc123' ),
    'missing element_id returns error'
);

$t->assert_not_null(
    Validator::validate( [ 'element_id' => '', 'changes' => [ 'settings' => [ 'alt' => 'x' ] ] ], 'abc123' ),
    'empty element_id returns error'
);

$t->assert_not_null(
    Validator::validate( minimal_patch( 'wrong_id' ), 'abc123' ),
    'mismatched element_id returns error'
);

$mismatch = Validator::validate( minimal_patch( 'wrong_id' ), 'abc123' );
$t->assert_contains( 'wrong_id', $mismatch ?? '', 'error message contains the offending id' );

// ============================================================
// Empty content check
// ============================================================

$t->suite( 'BricksPatchValidator — empty content' );

$t->assert_not_null(
    Validator::validate( [ 'element_id' => 'el1' ], 'el1' ),
    'patch with no change sections returns error'
);

$t->assert_not_null(
    Validator::validate( [ 'element_id' => 'el1', 'changes' => [], 'added_keys' => [], 'removed_keys' => [] ], 'el1' ),
    'patch with all empty sections returns error'
);

// ============================================================
// Section type checks
// ============================================================

$t->suite( 'BricksPatchValidator — section type checks' );

$t->assert_not_null(
    Validator::validate( [ 'element_id' => 'el1', 'changes' => 'not-an-array' ], 'el1' ),
    'non-array changes section returns error'
);

// ============================================================
// Disallowed top-level keys in sections
// ============================================================

$t->suite( 'BricksPatchValidator — disallowed keys' );

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'changes' => [ 'id' => 'hacked' ] ],
        'el1'
    ),
    'structural key "id" in changes returns error'
);

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'changes' => [ 'children' => [] ] ],
        'el1'
    ),
    'structural key "children" in changes returns error'
);

// ============================================================
// Blocked settings keys
// ============================================================

$t->suite( 'BricksPatchValidator — blocked settings keys' );

foreach ( [ 'code', 'query', 'hasLoop', '_gridArea', '_position' ] as $blocked ) {
    $t->assert_not_null(
        Validator::validate(
            [ 'element_id' => 'el1', 'changes' => [ 'settings' => [ $blocked => 'anything' ] ] ],
            'el1'
        ),
        "blocked settings key '{$blocked}' returns error"
    );
}

// ============================================================
// _cssCustom validation
// ============================================================

$t->suite( 'BricksPatchValidator — _cssCustom' );

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'changes' => [ 'settings' => [ '_cssCustom' => '#brxe-OTHER { color: red; }' ] ] ],
        'el1'
    ),
    '_cssCustom selector for different element returns error'
);

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'changes' => [ 'settings' => [ '_cssCustom' => '#brxe-el1 { color: red; } body { margin: 0; }' ] ] ],
        'el1'
    ),
    '_cssCustom with extra body selector returns error'
);

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'changes' => [ 'settings' => [ '_cssCustom' => 'a { text-decoration: none; }' ] ] ],
        'el1'
    ),
    '_cssCustom with bare tag selector returns error'
);

// ============================================================
// Attribute validation
// ============================================================

$t->suite( 'BricksPatchValidator — attribute allowlist' );

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'added_keys' => [ 'settings' => [ 'attributes' => [ 'onclick' => 'hack()' ] ] ] ],
        'el1'
    ),
    'disallowed attribute "onclick" returns error'
);

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'added_keys' => [ 'settings' => [ 'attributes' => [ 'style' => 'color:red' ] ] ] ],
        'el1'
    ),
    'disallowed attribute "style" returns error'
);

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'added_keys' => [ 'settings' => [ 'attributes' => [ 'class' => 'foo' ] ] ] ],
        'el1'
    ),
    'disallowed attribute "class" returns error'
);

// ============================================================
// Oversized patch
// ============================================================

$t->suite( 'BricksPatchValidator — size limit' );

// Build a patch with 45 settings keys (> MAX_KEYS of 40).
$fat_settings = [];
for ( $i = 0; $i < 45; $i++ ) {
    $fat_settings[ "key_{$i}" ] = "val_{$i}";
}

$t->assert_not_null(
    Validator::validate(
        [ 'element_id' => 'el1', 'changes' => [ 'settings' => $fat_settings ] ],
        'el1'
    ),
    'patch with too many keys returns error'
);
