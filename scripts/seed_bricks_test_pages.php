<?php
/**
 * Seed Bricks-based test pages for Accessibility Auditor manual/integration QA.
 *
 * Usage (inside wp-whittemore-lab):
 *   docker compose run --rm wpcli eval-file \
 *     /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php \
 *     --path=/var/www/html --allow-root
 *
 * Optional filters:
 *   PowerShell:
 *     $env:AA_SEED_LIST='1'
 *     $env:AA_SEED_SCENARIO='link-name-inline'
 *     $env:AA_SEED_RULE='image-alt'
 *     $env:AA_SEED_COMPONENT='image'
 *   Bash:
 *     AA_SEED_LIST=1
 *     AA_SEED_SCENARIO=link-name-inline
 *     AA_SEED_RULE=image-alt
 *     AA_SEED_COMPONENT=image
 *
 * What it does:
 * - Creates/updates a catalog of Bricks fixture pages
 * - Writes Bricks element trees into the active Bricks content meta key + `bricks_data`
 * - Attaches rule/component/strategy metadata for manual QA and E2E targeting
 * - Supports deterministic filtering so we can seed a single scenario or a subset
 *
 * Notes:
 * - This script seeds Bricks JSON (`bricks_data`) for testing plugin internals.
 * - Visual rendering depends on the active Bricks version/theme and may vary.
 * - The catalog is intentionally scenario-driven so it can back both CLI seeding and a future WP admin UI.
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
 * Parse custom `-- --key=value` style args passed through WP-CLI eval-file.
 */
function aa_seed_cli_args(): array {
    $args = [];

    $env_map = [
        'AA_SEED_LIST'      => 'list',
        'AA_SEED_SCENARIO'  => 'scenario',
        'AA_SEED_RULE'      => 'rule',
        'AA_SEED_COMPONENT' => 'component',
        'AA_SEED_STRATEGY'  => 'strategy',
    ];

    foreach ( $env_map as $env_key => $arg_key ) {
        $value = getenv( $env_key );
        if ( $value === false || $value === '' ) {
            continue;
        }

        $args[ $arg_key ] = $value === '1' ? true : trim( (string) $value );
    }

    foreach ( $_SERVER['argv'] ?? [] as $argv ) {
        if ( strpos( (string) $argv, '--' ) !== 0 ) {
            continue;
        }

        $arg = substr( (string) $argv, 2 );

        if ( $arg === '' || $arg === 'path=/var/www/html' || $arg === 'allow-root' ) {
            continue;
        }

        if ( strpos( $arg, '=' ) === false ) {
            $args[ $arg ] = true;
            continue;
        }

        list( $key, $value ) = explode( '=', $arg, 2 );
        $args[ $key ] = trim( (string) $value );
    }

    return $args;
}

function aa_seed_unique_id( string $prefix ): string {
    static $counters = [];

    if ( ! isset( $counters[ $prefix ] ) ) {
        $counters[ $prefix ] = 1;
    }

    $id = sprintf( '%s_%02d', $prefix, $counters[ $prefix ] );
    $counters[ $prefix ]++;

    return $id;
}

function aa_seed_element( string $name, array $settings = [], array $children = [], $parent = 0, ?string $id = null ): array {
    return [
        'id'       => $id ?: aa_seed_unique_id( substr( preg_replace( '/[^a-z]/i', '', $name ) ?: 'el', 0, 6 ) ),
        'name'     => $name,
        'parent'   => $parent,
        'children' => $children,
        'settings' => $settings,
    ];
}

function aa_seed_section( string $id, array $children = [], array $settings = [] ): array {
    return aa_seed_element( 'section', $settings, $children, 0, $id );
}

function aa_seed_container( string $id, string $parent, array $children = [], array $settings = [] ): array {
    return aa_seed_element( 'container', $settings, $children, $parent, $id );
}

function aa_seed_heading( string $id, string $parent, string $text, string $tag = 'h2', array $settings = [] ): array {
    return aa_seed_element(
        'heading',
        array_merge(
            [
                'text' => $text,
                'tag'  => $tag,
            ],
            $settings
        ),
        [],
        $parent,
        $id
    );
}

function aa_seed_text_basic( string $id, string $parent, string $html, array $settings = [] ): array {
    return aa_seed_element(
        'text-basic',
        array_merge(
            [
                'text' => $html,
            ],
            $settings
        ),
        [],
        $parent,
        $id
    );
}

function aa_seed_button( string $id, string $parent, array $settings = [] ): array {
    return aa_seed_element(
        'button',
        array_merge(
            [
                'text' => 'Learn more',
                'url'  => [ 'url' => '#' ],
            ],
            $settings
        ),
        [],
        $parent,
        $id
    );
}

function aa_seed_icon( string $id, string $parent, array $settings = [] ): array {
    return aa_seed_element(
        'icon',
        array_merge(
            [
                'icon'       => 'ti-star',
                'url'        => [ 'url' => '#' ],
                'attributes' => [],
            ],
            $settings
        ),
        [],
        $parent,
        $id
    );
}

function aa_seed_image( string $id, string $parent, string $src, array $settings = [] ): array {
    return aa_seed_element(
        'image',
        array_merge(
            [
                'image' => [ 'url' => $src ],
            ],
            $settings
        ),
        [],
        $parent,
        $id
    );
}

function aa_seed_block( string $id, string $parent, array $children = [], array $settings = [] ): array {
    return aa_seed_element( 'block', $settings, $children, $parent, $id );
}

function aa_seed_form( string $id, string $parent, array $settings = [] ): array {
    return aa_seed_element(
        'form',
        array_merge(
            [
                'fields' => [],
            ],
            $settings
        ),
        [],
        $parent,
        $id
    );
}

function aa_seed_code( string $id, string $parent, string $code ): array {
    return aa_seed_element(
        'code',
        [
            'executeCode' => false,
            'code'        => $code,
        ],
        [],
        $parent,
        $id
    );
}

function aa_seed_page_shell( string $slug, string $hero_title, string $intro_html ): array {
    $section_id   = aa_seed_unique_id( $slug . '_sec' );
    $container_id = aa_seed_unique_id( $slug . '_cnt' );
    $heading_id   = aa_seed_unique_id( $slug . '_hed' );
    $intro_id     = aa_seed_unique_id( $slug . '_txt' );

    return [
        aa_seed_section(
            $section_id,
            [ $container_id ],
            [
                '_padding'    => [ 'top' => '3rem', 'bottom' => '3rem' ],
                '_background' => [ 'color' => [ 'hex' => '#f7f5ef' ] ],
            ]
        ),
        aa_seed_container(
            $container_id,
            $section_id,
            [ $heading_id, $intro_id ],
            [
                '_direction' => 'column',
                '_rowGap'    => '1rem',
            ]
        ),
        aa_seed_heading( $heading_id, $container_id, $hero_title, 'h1' ),
        aa_seed_text_basic( $intro_id, $container_id, $intro_html ),
    ];
}

function aa_seed_add_to_root_container( array $elements, array $child_elements ): array {
    $container_index = null;

    foreach ( $elements as $index => $element ) {
        if ( ( $element['name'] ?? '' ) === 'container' && ( $element['parent'] ?? 0 ) !== 0 ) {
            $container_index = $index;
            break;
        }
    }

    if ( $container_index === null ) {
        return array_merge( $elements, $child_elements );
    }

    foreach ( $child_elements as $child ) {
        $elements[ $container_index ]['children'][] = $child['id'];
    }

    return array_merge( $elements, $child_elements );
}

function aa_seed_fixture_catalog(): array {
    $placeholder_hero = '<p>Seeded fixture page for deterministic Bricks accessibility testing.</p>';

    $catalog = [];

    $catalog[] = [
        'slug'       => 'aa-fixture-image-alt',
        'title'      => 'AA Fixture - Image Alt',
        'scenario'   => 'image-alt-basic',
        'rule_ids'   => [ 'image-alt' ],
        'components' => [ 'image' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Image element without altText for direct auto-fix coverage.',
        'post_html'  => '<p>Fixture page for image-alt rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'imgalt', 'Image Alt Fixture', $placeholder_hero ),
            [
                aa_seed_image( 'img_alt_01', 'imgalt_cnt_01', 'https://via.placeholder.com/640x360?text=Hero+Image' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-input-image-alt',
        'title'      => 'AA Fixture - Input Image Alt',
        'scenario'   => 'input-image-alt-banner',
        'rule_ids'   => [ 'input-image-alt' ],
        'components' => [ 'image', 'button' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Clickable image-style CTA with missing alt text for form submit/input-image coverage.',
        'post_html'  => '<p>Fixture page for input-image-alt rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'inputimg', 'Input Image Alt Fixture', $placeholder_hero ),
            [
                aa_seed_image(
                    'input_img_01',
                    'inputimg_cnt_01',
                    'https://via.placeholder.com/200x60?text=Submit',
                    [
                        'link' => [ 'type' => 'external', 'url' => '#' ],
                    ]
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-link-name',
        'title'      => 'AA Fixture - Link Name',
        'scenario'   => 'link-name-inline',
        'rule_ids'   => [ 'link-name' ],
        'components' => [ 'text-basic' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Inline empty link inside text-basic to exercise HTML fallback mapping.',
        'post_html'  => '<p>Fixture page for link-name rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'linkname', 'Link Name Fixture', $placeholder_hero ),
            [
                aa_seed_text_basic( 'txt_link_01', 'linkname_cnt_01', '<p>Call to action: <a href="#empty-link"></a></p>' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-link-name-button',
        'title'      => 'AA Fixture - Link Name Button',
        'scenario'   => 'link-name-button',
        'rule_ids'   => [ 'link-name' ],
        'components' => [ 'button' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Button-style link with missing accessible name for direct selector mapping.',
        'post_html'  => '<p>Fixture page for link-name button variant.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'linkbtn', 'Link Name Button Fixture', $placeholder_hero ),
            [
                aa_seed_button(
                    'link_btn_01',
                    'linkbtn_cnt_01',
                    [
                        'text' => '',
                        'url'  => [ 'url' => '#' ],
                    ]
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-color-contrast',
        'title'      => 'AA Fixture - Color Contrast',
        'scenario'   => 'color-contrast-inline',
        'rule_ids'   => [ 'color-contrast' ],
        'components' => [ 'text-basic' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Inline low-contrast text fixture for detect + guided remediation smoke tests.',
        'post_html'  => '<p>Fixture page for color-contrast rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'contrast', 'Color Contrast Fixture', $placeholder_hero ),
            [
                aa_seed_text_basic(
                    'txt_cc_01',
                    'contrast_cnt_01',
                    '<p style="color:#cfcfcf;background:#ffffff;padding:8px">Low contrast text fixture</p>'
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-frame-title',
        'title'      => 'AA Fixture - Frame Title',
        'scenario'   => 'frame-title-inline',
        'rule_ids'   => [ 'frame-title' ],
        'components' => [ 'text-basic', 'video' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Inline iframe without title to test frame-title mapping and patching.',
        'post_html'  => '<p>Fixture page for frame-title rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'frame', 'Frame Title Fixture', $placeholder_hero ),
            [
                aa_seed_text_basic(
                    'txt_iframe_01',
                    'frame_cnt_01',
                    '<iframe src="https://example.com" width="300" height="150"></iframe>'
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-button-name',
        'title'      => 'AA Fixture - Button Name',
        'scenario'   => 'button-name-empty',
        'rule_ids'   => [ 'button-name' ],
        'components' => [ 'button' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Empty Bricks button for direct button-name patch testing.',
        'post_html'  => '<p>Fixture page for button-name rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'btnname', 'Button Name Fixture', $placeholder_hero ),
            [
                aa_seed_button(
                    'btn_name_01',
                    'btnname_cnt_01',
                    [
                        'text' => '',
                        'url'  => [ 'url' => '#' ],
                    ]
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-aria-label',
        'title'      => 'AA Fixture - Aria Label',
        'scenario'   => 'aria-label-icon',
        'rule_ids'   => [ 'aria-label' ],
        'components' => [ 'icon' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Icon-only interactive element with empty aria-label.',
        'post_html'  => '<p>Fixture page for aria-label rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'arialabel', 'Aria Label Fixture', $placeholder_hero ),
            [
                aa_seed_icon(
                    'icon_link_01',
                    'arialabel_cnt_01',
                    [
                        'attributes' => [
                            'aria-label' => '',
                        ],
                    ]
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-aria-labelledby',
        'title'      => 'AA Fixture - Aria Labelledby',
        'scenario'   => 'aria-labelledby-missing',
        'rule_ids'   => [ 'aria-labelledby' ],
        'components' => [ 'icon', 'text-basic' ],
        'strategy'   => 'flagged',
        'notes'      => 'Flagged auto-fix candidate that still needs ID existence validation.',
        'post_html'  => '<p>Fixture page for aria-labelledby rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'arialby', 'Aria Labelledby Fixture', $placeholder_hero ),
            [
                aa_seed_text_basic( 'txt_lblby_label_01', 'arialby_cnt_01', '<p id="feature-label">Featured service</p>' ),
                aa_seed_text_basic( 'txt_lblby_target_01', 'arialby_cnt_01', '<a href="#" aria-labelledby=""></a>' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-aria-hidden-focus',
        'title'      => 'AA Fixture - Aria Hidden Focus',
        'scenario'   => 'aria-hidden-focus-inline',
        'rule_ids'   => [ 'aria-hidden-focus' ],
        'components' => [ 'text-basic' ],
        'strategy'   => 'flagged',
        'notes'      => 'Focusable element inside aria-hidden wrapper for conservative auto-fix review.',
        'post_html'  => '<p>Fixture page for aria-hidden-focus rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'hiddenfocus', 'Aria Hidden Focus Fixture', $placeholder_hero ),
            [
                aa_seed_text_basic(
                    'txt_hidden_focus_01',
                    'hiddenfocus_cnt_01',
                    '<div aria-hidden="true"><a href="#focusable">Focusable hidden link</a></div>'
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-heading-order',
        'title'      => 'AA Fixture - Heading Order',
        'scenario'   => 'heading-order-skip',
        'rule_ids'   => [ 'heading-order' ],
        'components' => [ 'heading' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Semantic heading-order issue that should stay guided-only.',
        'post_html'  => '<p>Fixture page for heading-order rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'headingord', 'Heading Order Fixture', '<p>This fixture intentionally skips from H1 to H4.</p>' ),
            [
                aa_seed_heading( 'heading_skip_01', 'headingord_cnt_01', 'Subsection rendered as H4', 'h4' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-label',
        'title'      => 'AA Fixture - Label',
        'scenario'   => 'label-missing',
        'rule_ids'   => [ 'label' ],
        'components' => [ 'form', 'text-basic' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Form control label issue for guided remediation testing.',
        'post_html'  => '<p>Fixture page for label rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'labelcase', 'Label Fixture', $placeholder_hero ),
            [
                aa_seed_text_basic( 'txt_label_01', 'labelcase_cnt_01', '<input type="email" placeholder="Email address">' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-document-title',
        'title'      => 'AA Fixture - Document Title',
        'scenario'   => 'document-title-site-scope',
        'rule_ids'   => [ 'document-title' ],
        'components' => [ 'site-scope' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Site/theme scope issue outside Bricks patching.',
        'post_html'  => '<p>Fixture page for document-title rule.</p>',
        'bricks'     => aa_seed_page_shell( 'doctitle', 'Document Title Fixture', '<p>Used to confirm guided-only handling for site-scope issues.</p>' ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-html-lang',
        'title'      => 'AA Fixture - HTML Lang',
        'scenario'   => 'html-has-lang-site-scope',
        'rule_ids'   => [ 'html-has-lang' ],
        'components' => [ 'site-scope' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Site/theme scope issue outside page-level Bricks patching.',
        'post_html'  => '<p>Fixture page for html-has-lang rule.</p>',
        'bricks'     => aa_seed_page_shell( 'htmllang', 'HTML Lang Fixture', '<p>Used to confirm guided-only handling for site-scope issues.</p>' ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-gallery-image-alt',
        'title'      => 'AA Fixture - Gallery Image Alt',
        'scenario'   => 'gallery-image-alt-grid',
        'rule_ids'   => [ 'image-alt' ],
        'components' => [ 'gallery', 'image' ],
        'strategy'   => 'auto-fix',
        'notes'      => 'Gallery-style grid using image elements with missing alt coverage.',
        'post_html'  => '<p>Fixture page for gallery image-alt rule.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'galleryalt', 'Gallery Image Alt Fixture', $placeholder_hero ),
            [
                aa_seed_block( 'gallery_block_01', 'galleryalt_cnt_01', [ 'gallery_img_01', 'gallery_img_02' ], [ '_rowGap' => '1rem' ] ),
                aa_seed_image( 'gallery_img_01', 'gallery_block_01', 'https://via.placeholder.com/300x180?text=Gallery+1' ),
                aa_seed_image( 'gallery_img_02', 'gallery_block_01', 'https://via.placeholder.com/300x180?text=Gallery+2' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-form-labels',
        'title'      => 'AA Fixture - Form Labels',
        'scenario'   => 'form-label-required',
        'rule_ids'   => [ 'label', 'aria-label' ],
        'components' => [ 'form' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Form-heavy page for guided remediation and future component coverage.',
        'post_html'  => '<p>Fixture page for form accessibility coverage.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'formlabels', 'Form Labels Fixture', $placeholder_hero ),
            [
                aa_seed_form( 'form_01', 'formlabels_cnt_01', [ 'fields' => [] ] ),
                aa_seed_text_basic( 'form_markup_01', 'formlabels_cnt_01', '<input type="text" aria-label=""><input type="checkbox">' ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-accordion-structure',
        'title'      => 'AA Fixture - Accordion Structure',
        'scenario'   => 'accordion-structure',
        'rule_ids'   => [ 'aria-required-children', 'heading-order' ],
        'components' => [ 'accordion' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Accordion-family fixture to drive future keyboard/ARIA guidance coverage.',
        'post_html'  => '<p>Fixture page for accordion component coverage.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'accordion', 'Accordion Fixture', $placeholder_hero ),
            [
                aa_seed_element(
                    'accordion',
                    [
                        'items' => [
                            [
                                'title'   => '',
                                'content' => '<p>Accordion item content without a proper heading/button pattern.</p>',
                            ],
                            [
                                'title'   => 'Second item',
                                'content' => '<p>Additional accordion content.</p>',
                            ],
                        ],
                    ],
                    [],
                    'accordion_cnt_01',
                    'accordion_01'
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-tabs-structure',
        'title'      => 'AA Fixture - Tabs Structure',
        'scenario'   => 'tabs-structure',
        'rule_ids'   => [ 'aria-required-children', 'aria-required-parent' ],
        'components' => [ 'tabs' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Tabs-family fixture for future roles, states, and keyboard guidance coverage.',
        'post_html'  => '<p>Fixture page for tabs component coverage.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'tabsfixture', 'Tabs Fixture', $placeholder_hero ),
            [
                aa_seed_element(
                    'tabs',
                    [
                        'items' => [
                            [
                                'title'   => '',
                                'content' => '<p>Unnamed tab panel content.</p>',
                            ],
                            [
                                'title'   => 'Specifications',
                                'content' => '<p>Tab panel content.</p>',
                            ],
                        ],
                    ],
                    [],
                    'tabsfixture_cnt_01',
                    'tabs_01'
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-progressbar-name',
        'title'      => 'AA Fixture - Progress Bar Name',
        'scenario'   => 'progressbar-name',
        'rule_ids'   => [ 'aria-progressbar-name', 'color-contrast' ],
        'components' => [ 'progress-bar' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Progress bar component fixture for label/value semantics and contrast follow-up.',
        'post_html'  => '<p>Fixture page for progress bar component coverage.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'progressbar', 'Progress Bar Fixture', $placeholder_hero ),
            [
                aa_seed_element(
                    'progress-bar',
                    [
                        'value' => 62,
                        'label' => '',
                    ],
                    [],
                    'progressbar_cnt_01',
                    'progress_01'
                ),
            ]
        ),
    ];

    $catalog[] = [
        'slug'       => 'aa-fixture-code-embed-frame',
        'title'      => 'AA Fixture - Code Embed Frame',
        'scenario'   => 'code-embed-frame-title',
        'rule_ids'   => [ 'frame-title' ],
        'components' => [ 'code' ],
        'strategy'   => 'guided-only',
        'notes'      => 'Code block embed to exercise editor placeholder and guided-only edge-case handling.',
        'post_html'  => '<p>Fixture page for code/embed frame coverage.</p>',
        'bricks'     => aa_seed_add_to_root_container(
            aa_seed_page_shell( 'codeframe', 'Code Embed Fixture', $placeholder_hero ),
            [
                aa_seed_code( 'code_frame_01', 'codeframe_cnt_01', '<iframe src="https://example.com"></iframe>' ),
            ]
        ),
    ];

    return array_map( 'aa_seed_normalize_fixture', $catalog );
}

function aa_seed_normalize_fixture( array $fixture ): array {
    $fixture['scenario']   = (string) ( $fixture['scenario'] ?? $fixture['slug'] ?? '' );
    $fixture['rule_ids']   = array_values( array_unique( array_map( 'strval', $fixture['rule_ids'] ?? [] ) ) );
    $fixture['components'] = array_values( array_unique( array_map( 'strval', $fixture['components'] ?? [] ) ) );
    $fixture['strategy']   = (string) ( $fixture['strategy'] ?? 'guided-only' );
    $fixture['post_html']  = (string) ( $fixture['post_html'] ?? '' );
    $fixture['notes']      = (string) ( $fixture['notes'] ?? '' );
    $fixture['title']      = (string) ( $fixture['title'] ?? $fixture['slug'] ?? 'AA Fixture' );
    $fixture['slug']       = (string) ( $fixture['slug'] ?? sanitize_title( $fixture['title'] ) );
    $fixture['bricks']     = array_values( $fixture['bricks'] ?? [] );

    return $fixture;
}

function aa_seed_detect_bricks_content_meta_key(): string {
    if ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && is_string( BRICKS_DB_PAGE_CONTENT ) && BRICKS_DB_PAGE_CONTENT !== '' ) {
        return BRICKS_DB_PAGE_CONTENT;
    }

    $bricks_pages = get_posts(
        [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => 20,
            'meta_key'       => '_bricks_editor_mode',
            'meta_value'     => 'bricks',
            'fields'         => 'ids',
        ]
    );

    foreach ( $bricks_pages as $pid ) {
        $meta = get_post_meta( (int) $pid );
        foreach ( array_keys( $meta ) as $meta_key ) {
            if ( preg_match( '/^_bricks_page_content(_\d+)?$/', (string) $meta_key ) ) {
                return (string) $meta_key;
            }
        }
    }

    return '_bricks_page_content_2';
}

function aa_seed_fixture_matches_filters( array $fixture, array $args ): bool {
    if ( ! empty( $args['scenario'] ) && $fixture['scenario'] !== $args['scenario'] ) {
        return false;
    }

    if ( ! empty( $args['rule'] ) && ! in_array( $args['rule'], $fixture['rule_ids'], true ) ) {
        return false;
    }

    if ( ! empty( $args['component'] ) && ! in_array( $args['component'], $fixture['components'], true ) ) {
        return false;
    }

    if ( ! empty( $args['strategy'] ) && $fixture['strategy'] !== $args['strategy'] ) {
        return false;
    }

    return true;
}

function aa_seed_list_fixtures( array $fixtures ): void {
    $lines = [];

    foreach ( $fixtures as $fixture ) {
        $lines[] = sprintf(
            '%s | rules=%s | components=%s | strategy=%s',
            $fixture['scenario'],
            implode( ',', $fixture['rule_ids'] ),
            implode( ',', $fixture['components'] ),
            $fixture['strategy']
        );
    }

    sort( $lines );

    foreach ( $lines as $line ) {
        if ( class_exists( 'WP_CLI' ) ) {
            WP_CLI::log( $line );
        } else {
            echo $line . "\n";
        }
    }
}

function aa_seed_upsert_fixture_page( array $fixture, string $bricks_content_key ): array {
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
        $post_id       = wp_update_post( $postarr, true );
        $action        = 'updated';
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

    update_post_meta( (int) $post_id, '_bricks_editor_mode', 'bricks' );
    update_post_meta( (int) $post_id, '_bricks_template_type', 'content' );
    update_post_meta( (int) $post_id, $bricks_content_key, $fixture['bricks'] );
    update_post_meta( (int) $post_id, 'bricks_data', $fixture['bricks'] );
    update_post_meta( (int) $post_id, '_aa_fixture_rule_ids', $fixture['rule_ids'] );
    update_post_meta( (int) $post_id, '_aa_fixture_components', $fixture['components'] );
    update_post_meta( (int) $post_id, '_aa_fixture_strategy', $fixture['strategy'] );
    update_post_meta( (int) $post_id, '_aa_fixture_scenario', $fixture['scenario'] );
    update_post_meta( (int) $post_id, '_aa_fixture_notes', $fixture['notes'] );

    delete_post_meta( (int) $post_id, '_acss_scan_status' );
    delete_post_meta( (int) $post_id, '_acss_scan_score' );
    delete_post_meta( (int) $post_id, '_aa_scan_score' );
    delete_post_meta( (int) $post_id, '_aa_scan_summary' );
    delete_post_meta( (int) $post_id, '_aa_scan_results' );

    return [
        'ok'         => true,
        'action'     => $action,
        'post_id'    => (int) $post_id,
        'slug'       => $fixture['slug'],
        'title'      => $fixture['title'],
        'scenario'   => $fixture['scenario'],
        'rule_ids'   => $fixture['rule_ids'],
        'components' => $fixture['components'],
        'strategy'   => $fixture['strategy'],
        'bricks_ct'  => count( $fixture['bricks'] ),
        'bricks_key' => $bricks_content_key,
    ];
}

function aa_seed_log( string $message, string $level = 'log' ): void {
    if ( class_exists( 'WP_CLI' ) ) {
        switch ( $level ) {
            case 'success':
                WP_CLI::success( $message );
                return;
            case 'warning':
                WP_CLI::warning( $message );
                return;
            default:
                WP_CLI::log( $message );
                return;
        }
    }

    $prefix = strtoupper( $level );
    echo sprintf( '[%s] %s', $prefix, $message ) . "\n";
}

if ( ! defined( 'AA_SEED_LIBRARY_ONLY' ) ) {
    $args              = aa_seed_cli_args();
    $all_fixtures      = aa_seed_fixture_catalog();
    $selected_fixtures = array_values(
        array_filter(
            $all_fixtures,
            static function ( array $fixture ) use ( $args ): bool {
                return aa_seed_fixture_matches_filters( $fixture, $args );
            }
        )
    );

    if ( ! empty( $args['list'] ) ) {
        aa_seed_list_fixtures( $all_fixtures );
        return;
    }

    if ( empty( $selected_fixtures ) ) {
        aa_seed_log( 'No fixture scenarios matched the provided filters.', 'warning' );
        aa_seed_log( 'Try -- --list to see available scenarios.', 'log' );
        return;
    }

    $bricks_content_key = aa_seed_detect_bricks_content_meta_key();
    $results            = [];

    foreach ( $selected_fixtures as $fixture ) {
        $results[] = aa_seed_upsert_fixture_page( $fixture, $bricks_content_key );
    }

    foreach ( $results as $row ) {
        if ( ! $row['ok'] ) {
            aa_seed_log( sprintf( '[%s] %s', $row['slug'], $row['message'] ), 'warning' );
            continue;
        }

        aa_seed_log(
            sprintf(
                '%s #%d %s (%s) | scenario=%s | rules=%s | components=%s | strategy=%s | bricks=%d | key=%s',
                strtoupper( $row['action'] ),
                $row['post_id'],
                $row['title'],
                $row['slug'],
                $row['scenario'],
                implode( ',', $row['rule_ids'] ),
                implode( ',', $row['components'] ),
                $row['strategy'],
                $row['bricks_ct'],
                $row['bricks_key']
            ),
            'success'
        );
    }

    $seeded_rules      = [];
    $seeded_components = [];

    foreach ( $selected_fixtures as $fixture ) {
        $seeded_rules      = array_merge( $seeded_rules, $fixture['rule_ids'] );
        $seeded_components = array_merge( $seeded_components, $fixture['components'] );
    }

    $seeded_rules      = array_values( array_unique( $seeded_rules ) );
    $seeded_components = array_values( array_unique( $seeded_components ) );
    sort( $seeded_rules );
    sort( $seeded_components );

    aa_seed_log( sprintf( 'Seeded %d fixture page(s).', count( $selected_fixtures ) ) );
    aa_seed_log( 'Rules covered: ' . implode( ', ', $seeded_rules ) );
    aa_seed_log( 'Components covered: ' . implode( ', ', $seeded_components ) );
    aa_seed_log( 'Open Pages and test in Bricks editor with Accessibility Auditor.' );
    aa_seed_log( 'Use AA_SEED_LIST=1, AA_SEED_RULE, AA_SEED_COMPONENT, AA_SEED_SCENARIO, or AA_SEED_STRATEGY for targeted seeding.' );
}
