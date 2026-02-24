<?php
/**
 * Scenario-style unit tests for current auto-fix rule coverage.
 *
 * These tests exercise the chain:
 *   axe issue -> BricksElementFinder::from_issue -> BricksPatchValidator -> BricksPatchApplier
 *
 * They do not call Claude. Instead, they validate the patch shapes we expect the
 * current auto-fix flow to accept/apply for supported rules.
 */

use Accessibility_Auditor\BricksElementFinder as Finder;
use Accessibility_Auditor\BricksPatchValidator as Validator;
use Accessibility_Auditor\BricksPatchApplier as Applier;

function afs_tree_image_alt(): array {
    return [
        [
            'id'       => 'img_alt_01',
            'name'     => 'image',
            'settings' => [
                'image' => [ 'url' => 'hero.jpg' ],
            ],
            'children' => [],
        ],
    ];
}

function afs_issue_image_alt(): array {
    return [
        'id'    => 'image-alt',
        'nodes' => [
            [
                'target' => [ '#brxe-img_alt_01' ],
                'html'   => '<img id="brxe-img_alt_01" src="hero.jpg">',
            ],
        ],
    ];
}

function afs_tree_link_name_variants(): array {
    return [
        [
            'id'       => 'link_btn_01',
            'name'     => 'button',
            'settings' => [
                'url' => [ 'url' => '#' ],
            ],
            'children' => [],
        ],
        [
            'id'       => 'txt_link_01',
            'name'     => 'text-basic',
            'settings' => [
                'text' => '<a href="#empty-link"></a>',
            ],
            'children' => [],
        ],
    ];
}

function afs_issue_link_name_selector(): array {
    return [
        'id'    => 'link-name',
        'nodes' => [
            [
                'target' => [ '#brxe-link_btn_01' ],
                'html'   => '<a id="brxe-link_btn_01" href="#"></a>',
            ],
        ],
    ];
}

function afs_issue_link_name_fallback(): array {
    return [
        'id'    => 'link-name',
        'nodes' => [
            [
                'target' => [ 'a' ],
                'html'   => '<a href="#empty-link"></a>',
            ],
        ],
    ];
}

function afs_tree_button_name(): array {
    return [
        [
            'id'       => 'btn_name_01',
            'name'     => 'button',
            'settings' => [
                'text' => '',
                'url'  => [ 'url' => '#' ],
            ],
            'children' => [],
        ],
    ];
}

function afs_issue_button_name(): array {
    return [
        'id'    => 'button-name',
        'nodes' => [
            [
                'target' => [ '#brxe-btn_name_01' ],
                'html'   => '<a id="brxe-btn_name_01" href="#"></a>',
            ],
        ],
    ];
}

function afs_tree_aria_label(): array {
    return [
        [
            'id'       => 'icon_link_01',
            'name'     => 'icon',
            'settings' => [
                'icon' => 'ti-star',
                'attributes' => [],
            ],
            'children' => [],
        ],
    ];
}

function afs_issue_aria_label(): array {
    return [
        'id'    => 'aria-label',
        'nodes' => [
            [
                'target' => [ 'a' ],
                'html'   => '<a id="brxe-icon_link_01" href="#" aria-label=""></a>',
            ],
        ],
    ];
}

$t->suite( 'Auto-fix scenarios — image-alt' );

$elements_img = afs_tree_image_alt();
$targets_img  = Finder::from_issue( $elements_img, afs_issue_image_alt() );

$t->assert_equals( 1, count( $targets_img ), 'image-alt maps issue to one Bricks element' );
$t->assert_equals( 'img_alt_01', $targets_img[0]['id'] ?? null, 'image-alt maps to expected element id' );

$image_patch = [
    'element_id' => 'img_alt_01',
    'changes'    => [
        'settings' => [
            'altText' => 'Hero banner showing team collaboration',
        ],
    ],
];

$t->assert_null(
    Validator::validate( $image_patch, 'img_alt_01' ),
    'image-alt patch passes validator'
);

$patched_img = Applier::apply( $targets_img[0], $image_patch );
$t->assert_equals(
    'Hero banner showing team collaboration',
    $patched_img['settings']['altText'] ?? null,
    'image-alt patch sets settings.altText'
);

$t->suite( 'Auto-fix scenarios — link-name' );

$elements_link = afs_tree_link_name_variants();

$targets_link_selector = Finder::from_issue( $elements_link, afs_issue_link_name_selector() );
$t->assert_equals( 1, count( $targets_link_selector ), 'link-name selector path maps one element' );
$t->assert_equals( 'link_btn_01', $targets_link_selector[0]['id'] ?? null, 'link-name selector path maps expected id' );

$link_patch = [
    'element_id' => 'link_btn_01',
    'changes'    => [
        'settings' => [
            'url' => [ 'ariaLabel' => 'Read more about pricing' ],
        ],
    ],
    'added_keys' => [
        'settings' => [
            'attributes' => [
                'aria-label' => 'Read more about pricing',
            ],
        ],
    ],
];

$t->assert_null(
    Validator::validate( $link_patch, 'link_btn_01' ),
    'link-name patch passes validator for button/link element'
);

$patched_link = Applier::apply( $targets_link_selector[0], $link_patch );
$t->assert_equals(
    'Read more about pricing',
    $patched_link['settings']['url']['ariaLabel'] ?? null,
    'link-name patch sets settings.url.ariaLabel'
);

$targets_link_fallback = Finder::from_issue( $elements_link, afs_issue_link_name_fallback() );
$t->assert_equals( 1, count( $targets_link_fallback ), 'link-name fallback maps one element from inline HTML' );
$t->assert_equals( 'txt_link_01', $targets_link_fallback[0]['id'] ?? null, 'link-name fallback maps text-basic element' );

$t->suite( 'Auto-fix scenarios — button-name' );

$elements_btn = afs_tree_button_name();
$targets_btn  = Finder::from_issue( $elements_btn, afs_issue_button_name() );

$t->assert_equals( 1, count( $targets_btn ), 'button-name maps issue to one Bricks element' );
$t->assert_equals( 'btn_name_01', $targets_btn[0]['id'] ?? null, 'button-name maps to expected element id' );

$button_patch = [
    'element_id' => 'btn_name_01',
    'changes'    => [
        'settings' => [
            'url' => [ 'ariaLabel' => 'Open contact form' ],
        ],
    ],
    'added_keys' => [
        'settings' => [
            'attributes' => [
                'aria-label' => 'Open contact form',
            ],
        ],
    ],
];

$t->assert_null(
    Validator::validate( $button_patch, 'btn_name_01' ),
    'button-name patch passes validator'
);

$patched_btn = Applier::apply( $targets_btn[0], $button_patch );
$t->assert_equals(
    'Open contact form',
    $patched_btn['settings']['url']['ariaLabel'] ?? null,
    'button-name patch sets settings.url.ariaLabel'
);
$t->assert_equals(
    'Open contact form',
    $patched_btn['settings']['attributes']['aria-label'] ?? null,
    'button-name patch sets aria-label attribute'
);

$tree_after_btn = afs_tree_button_name();
Finder::update( $tree_after_btn, 'btn_name_01', $patched_btn );
$updated_btn = Finder::find( $tree_after_btn, 'btn_name_01' );
$t->assert_equals(
    'Open contact form',
    $updated_btn['settings']['attributes']['aria-label'] ?? null,
    'button-name patched element persists after tree update'
);

$t->suite( 'Auto-fix scenarios — aria-label' );

$elements_aria = afs_tree_aria_label();
$targets_aria  = Finder::from_issue( $elements_aria, afs_issue_aria_label() );

$t->assert_equals( 1, count( $targets_aria ), 'aria-label maps issue to one Bricks element' );
$t->assert_equals( 'icon_link_01', $targets_aria[0]['id'] ?? null, 'aria-label maps to expected element id' );

$aria_patch = [
    'element_id' => 'icon_link_01',
    'added_keys' => [
        'settings' => [
            'attributes' => [
                'aria-label' => 'Open social profile',
                'role'       => 'img',
            ],
        ],
    ],
];

$t->assert_null(
    Validator::validate( $aria_patch, 'icon_link_01' ),
    'aria-label patch passes validator'
);

$patched_aria = Applier::apply( $targets_aria[0], $aria_patch );
$t->assert_equals(
    'Open social profile',
    $patched_aria['settings']['attributes']['aria-label'] ?? null,
    'aria-label patch sets aria-label attribute'
);
$t->assert_equals(
    'img',
    $patched_aria['settings']['attributes']['role'] ?? null,
    'aria-label patch may set role attribute within allowlist'
);

$t->suite( 'Auto-fix scenarios — input-image-alt' );

$elements_input_img = [
    [
        'id'       => 'input_img_01',
        'name'     => 'image',
        'settings' => [
            'image' => [ 'url' => 'submit.png' ],
        ],
        'children' => [],
    ],
];

$issue_input_img = [
    'id'    => 'input-image-alt',
    'nodes' => [
        [
            'target' => [ '#brxe-input_img_01' ],
            'html'   => '<input id="brxe-input_img_01" type="image" src="submit.png">',
        ],
    ],
];

$targets_input_img = Finder::from_issue( $elements_input_img, $issue_input_img );
$t->assert_equals( 1, count( $targets_input_img ), 'input-image-alt maps issue to one Bricks element' );
$t->assert_equals( 'input_img_01', $targets_input_img[0]['id'] ?? null, 'input-image-alt maps to expected element id' );

$input_img_patch = [
    'element_id' => 'input_img_01',
    'changes'    => [
        'settings' => [
            'altText' => 'Submit form',
        ],
    ],
];

$t->assert_null(
    Validator::validate( $input_img_patch, 'input_img_01' ),
    'input-image-alt patch passes validator'
);

$patched_input_img = Applier::apply( $targets_input_img[0], $input_img_patch );
$t->assert_equals(
    'Submit form',
    $patched_input_img['settings']['altText'] ?? null,
    'input-image-alt patch sets settings.altText'
);

$t->suite( 'Auto-fix scenarios — frame-title' );

$elements_frame = [
    [
        'id'       => 'frame_01',
        'name'     => 'text-basic',
        'settings' => [
            'text' => '<iframe src="https://example.com/embed"></iframe>',
        ],
        'children' => [],
    ],
];

$issue_frame = [
    'id'    => 'frame-title',
    'nodes' => [
        [
            'target' => [ 'iframe' ],
            'html'   => '<iframe id="brxe-frame_01" src="https://example.com/embed"></iframe>',
        ],
    ],
];

$targets_frame = Finder::from_issue( $elements_frame, $issue_frame );
$t->assert_equals( 1, count( $targets_frame ), 'frame-title maps issue to one Bricks element' );
$t->assert_equals( 'frame_01', $targets_frame[0]['id'] ?? null, 'frame-title maps to expected element id' );

$frame_patch = [
    'element_id' => 'frame_01',
    'added_keys' => [
        'settings' => [
            'attributes' => [
                'title' => 'Map location',
            ],
        ],
    ],
];

$t->assert_null(
    Validator::validate( $frame_patch, 'frame_01' ),
    'frame-title patch passes validator'
);

$patched_frame = Applier::apply( $targets_frame[0], $frame_patch );
$t->assert_equals(
    'Map location',
    $patched_frame['settings']['attributes']['title'] ?? null,
    'frame-title patch sets title attribute'
);
