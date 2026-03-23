<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SeedFixturesAdmin {
    const PAGE_SLUG = 'aa-seed-fixtures';

    public static function init() {
        if ( ! self::is_enabled() ) {
            return;
        }

        add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
    }

    private static function is_enabled(): bool {
        if ( defined( 'AA_ENABLE_SEED_FIXTURES' ) ) {
            return (bool) AA_ENABLE_SEED_FIXTURES;
        }

        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

    private static function load_seed_library(): void {
        if ( function_exists( 'aa_seed_fixture_catalog' ) ) {
            return;
        }

        if ( ! defined( 'AA_SEED_LIBRARY_ONLY' ) ) {
            define( 'AA_SEED_LIBRARY_ONLY', true );
        }

        require_once AA_PLUGIN_DIR . 'scripts/seed_bricks_test_pages.php';
    }

    public static function register_page() {
        add_submenu_page(
            Admin::MENU_SLUG,
            __( 'Seed Fixtures', 'accessibility-auditor' ),
            __( 'Seed Fixtures', 'accessibility-auditor' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        self::load_seed_library();

        $notice            = null;
        $fixtures          = aa_seed_fixture_catalog();
        $component_summary = self::component_summary( $fixtures );
        $filter_values     = self::current_filter_values();
        $visible_fixtures  = self::filter_fixtures( $fixtures, $filter_values );

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $notice = self::handle_post( $fixtures );
            $filter_values    = self::current_filter_values();
            $visible_fixtures = self::filter_fixtures( $fixtures, $filter_values );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Seed Fixtures', 'accessibility-auditor' ) . '</h1>';
        echo '<p style="max-width:920px;">' . esc_html__( 'Dev-only fixture seeding for Bricks pages. Use this to generate deterministic scan and auto-fix targets without touching production content flows.', 'accessibility-auditor' ) . '</p>';

        if ( is_array( $notice ) ) {
            echo '<div class="' . esc_attr( $notice['class'] ) . '"><p>' . esc_html( $notice['text'] ) . '</p></div>';
        }

        echo '<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin:18px 0;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Quick Actions', 'accessibility-auditor' ) . '</h2>';
        echo '<p>' . esc_html__( 'These actions reseed matching pages and clear stored scan metadata for those pages.', 'accessibility-auditor' ) . '</p>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;">';
        self::render_action_form( __( 'Seed All', 'accessibility-auditor' ), [] );
        self::render_action_form( __( 'Seed Auto-Fix', 'accessibility-auditor' ), [ 'strategy' => 'auto-fix' ] );
        self::render_action_form( __( 'Seed Guided-Only', 'accessibility-auditor' ), [ 'strategy' => 'guided-only' ] );
        self::render_action_form( __( 'Seed Flagged', 'accessibility-auditor' ), [ 'strategy' => 'flagged' ] );
        echo '</div>';
        echo '</div>';

        echo '<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin:18px 0;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Catalog Filters', 'accessibility-auditor' ) . '</h2>';
        echo '<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
        self::render_select(
            'component',
            __( 'Component', 'accessibility-auditor' ),
            self::component_options( $fixtures ),
            $filter_values['component']
        );
        self::render_select(
            'rule',
            __( 'Rule', 'accessibility-auditor' ),
            self::rule_options( $fixtures ),
            $filter_values['rule']
        );
        self::render_select(
            'strategy',
            __( 'Strategy', 'accessibility-auditor' ),
            self::strategy_options( $fixtures ),
            $filter_values['strategy']
        );
        self::render_select(
            'scenario',
            __( 'Scenario', 'accessibility-auditor' ),
            self::scenario_options( $fixtures ),
            $filter_values['scenario']
        );
        echo '<p style="margin:0;"><button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'accessibility-auditor' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Reset', 'accessibility-auditor' ) . '</a></p>';
        echo '</form>';
        echo '</div>';

        echo '<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin:18px 0;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Component Coverage Snapshot', 'accessibility-auditor' ) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Component', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Scenarios', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Rules', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Top Strategy', 'accessibility-auditor' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $component_summary as $component => $summary ) {
            echo '<tr>';
            echo '<td><strong>' . esc_html( $component ) . '</strong></td>';
            echo '<td>' . esc_html( (string) $summary['scenario_count'] ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', $summary['rules'] ) ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', $summary['strategies'] ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Scenario', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Rules', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Components', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Strategy', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'accessibility-auditor' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $visible_fixtures as $fixture ) {
            $post = get_page_by_path( $fixture['slug'], OBJECT, 'page' );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $fixture['title'] ) . '</strong><br><code>' . esc_html( $fixture['scenario'] ) . '</code><br><span style="color:#50575e;">' . esc_html( $fixture['notes'] ) . '</span></td>';
            echo '<td>' . esc_html( implode( ', ', $fixture['rule_ids'] ) ) . '</td>';
            echo '<td>' . esc_html( implode( ', ', $fixture['components'] ) ) . '</td>';
            echo '<td>' . esc_html( $fixture['strategy'] ) . '</td>';
            echo '<td>';
            self::render_action_form( __( 'Seed This Scenario', 'accessibility-auditor' ), [ 'scenario' => $fixture['scenario'] ], true );
            if ( $post instanceof \WP_Post ) {
                echo '<div style="margin-top:8px;">';
                echo '<a class="button button-small" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Edit', 'accessibility-auditor' ) . '</a> ';
                echo '<a class="button button-small" href="' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=bricks' ) ) . '">' . esc_html__( 'Bricks', 'accessibility-auditor' ) . '</a>';
                echo '</div>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function current_filter_values(): array {
        return [
            'scenario'  => sanitize_text_field( wp_unslash( $_GET['scenario'] ?? '' ) ),
            'strategy'  => sanitize_text_field( wp_unslash( $_GET['strategy'] ?? '' ) ),
            'rule'      => sanitize_text_field( wp_unslash( $_GET['rule'] ?? '' ) ),
            'component' => sanitize_text_field( wp_unslash( $_GET['component'] ?? '' ) ),
        ];
    }

    private static function render_select( string $name, string $label, array $options, string $selected ) {
        echo '<label style="display:flex;flex-direction:column;gap:6px;min-width:180px;">';
        echo '<span>' . esc_html( $label ) . '</span>';
        echo '<select name="' . esc_attr( $name ) . '">';
        echo '<option value="">' . esc_html__( 'All', 'accessibility-auditor' ) . '</option>';
        foreach ( $options as $value => $text ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
        }
        echo '</select>';
        echo '</label>';
    }

    private static function render_action_form( string $label, array $filters, bool $small = false ) {
        echo '<form method="post" style="display:inline-block;margin:0;">';
        wp_nonce_field( 'aa_seed_fixture_action', '_aa_seed_fixture_nonce' );
        foreach ( $filters as $key => $value ) {
            echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
        }
        echo '<button type="submit" class="' . esc_attr( $small ? 'button button-small' : 'button button-secondary' ) . '">' . esc_html( $label ) . '</button>';
        echo '</form>';
    }

    private static function handle_post( array $fixtures ): array {
        check_admin_referer( 'aa_seed_fixture_action', '_aa_seed_fixture_nonce' );

        $filters = array_filter( [
            'scenario' => sanitize_text_field( wp_unslash( $_POST['scenario'] ?? '' ) ),
            'strategy' => sanitize_text_field( wp_unslash( $_POST['strategy'] ?? '' ) ),
            'rule'     => sanitize_text_field( wp_unslash( $_POST['rule'] ?? '' ) ),
            'component'=> sanitize_text_field( wp_unslash( $_POST['component'] ?? '' ) ),
        ] );

        $selected = self::filter_fixtures( $fixtures, $filters );

        if ( empty( $selected ) ) {
            return [
                'class' => 'notice notice-error',
                'text'  => __( 'No matching fixture scenarios found.', 'accessibility-auditor' ),
            ];
        }

        $bricks_content_key = aa_seed_detect_bricks_content_meta_key();
        $ok_count           = 0;

        foreach ( $selected as $fixture ) {
            $result = aa_seed_upsert_fixture_page( $fixture, $bricks_content_key );
            if ( ! empty( $result['ok'] ) ) {
                $ok_count++;
            }
        }

        return [
            'class' => 'notice notice-success',
            'text'  => sprintf( __( 'Seeded %d fixture page(s).', 'accessibility-auditor' ), $ok_count ),
        ];
    }

    private static function filter_fixtures( array $fixtures, array $filters ): array {
        return array_values( array_filter( $fixtures, static function( array $fixture ) use ( $filters ): bool {
            if ( ! empty( $filters['scenario'] ) && $fixture['scenario'] !== $filters['scenario'] ) {
                return false;
            }
            if ( ! empty( $filters['strategy'] ) && $fixture['strategy'] !== $filters['strategy'] ) {
                return false;
            }
            if ( ! empty( $filters['rule'] ) && ! in_array( $filters['rule'], $fixture['rule_ids'], true ) ) {
                return false;
            }
            if ( ! empty( $filters['component'] ) && ! in_array( $filters['component'], $fixture['components'], true ) ) {
                return false;
            }
            return true;
        } ) );
    }

    private static function component_summary( array $fixtures ): array {
        $summary = [];

        foreach ( $fixtures as $fixture ) {
            foreach ( $fixture['components'] as $component ) {
                if ( ! isset( $summary[ $component ] ) ) {
                    $summary[ $component ] = [
                        'scenario_count' => 0,
                        'rules'          => [],
                        'strategies'     => [],
                    ];
                }

                $summary[ $component ]['scenario_count']++;
                $summary[ $component ]['rules']      = array_values( array_unique( array_merge( $summary[ $component ]['rules'], $fixture['rule_ids'] ) ) );
                $summary[ $component ]['strategies'] = array_values( array_unique( array_merge( $summary[ $component ]['strategies'], [ $fixture['strategy'] ] ) ) );
            }
        }

        ksort( $summary );
        return $summary;
    }

    private static function component_options( array $fixtures ): array {
        $options = [];
        foreach ( $fixtures as $fixture ) {
            foreach ( $fixture['components'] as $component ) {
                $options[ $component ] = $component;
            }
        }
        ksort( $options );
        return $options;
    }

    private static function rule_options( array $fixtures ): array {
        $options = [];
        foreach ( $fixtures as $fixture ) {
            foreach ( $fixture['rule_ids'] as $rule_id ) {
                $options[ $rule_id ] = $rule_id;
            }
        }
        ksort( $options );
        return $options;
    }

    private static function strategy_options( array $fixtures ): array {
        $options = [];
        foreach ( $fixtures as $fixture ) {
            $options[ $fixture['strategy'] ] = $fixture['strategy'];
        }
        ksort( $options );
        return $options;
    }

    private static function scenario_options( array $fixtures ): array {
        $options = [];
        foreach ( $fixtures as $fixture ) {
            $options[ $fixture['scenario'] ] = $fixture['scenario'];
        }
        ksort( $options );
        return $options;
    }
}
