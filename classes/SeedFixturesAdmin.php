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
            __( 'Demo Pages', 'accessibility-auditor' ),
            __( 'Demo Pages', 'accessibility-auditor' ),
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
        $fixtures         = aa_seed_fixture_catalog();
        $filter_values    = self::current_filter_values();
        $visible_fixtures = self::filter_fixtures( $fixtures, $filter_values );

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $notice = self::handle_post( $fixtures );
            $filter_values    = self::current_filter_values();
            $visible_fixtures = self::filter_fixtures( $fixtures, $filter_values );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Demo Pages', 'accessibility-auditor' ) . '</h1>';
        echo '<p style="max-width:920px;font-size:14px;">' . esc_html__( 'Use this page to prepare safe demo pages for scans and AI fix walkthroughs.', 'accessibility-auditor' ) . '</p>';

        if ( is_array( $notice ) ) {
            echo '<div class="' . esc_attr( $notice['class'] ) . '"><p>' . esc_html( $notice['text'] ) . '</p></div>';
        }

        echo '<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin:18px 0;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Prepare Demo Pages', 'accessibility-auditor' ) . '</h2>';
        echo '<p>' . esc_html__( 'Start with planned AI fix pages for the clearest client walkthrough. These actions refresh the demo pages and clear old scan results.', 'accessibility-auditor' ) . '</p>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;">';
        self::render_action_form( __( 'Prepare Planned AI Fix Pages', 'accessibility-auditor' ), [ 'strategy' => 'auto-fix' ] );
        self::render_action_form( __( 'Prepare Guide-Only Demos', 'accessibility-auditor' ), [ 'strategy' => 'guided-only' ] );
        self::render_action_form( __( 'Prepare All Demo Pages', 'accessibility-auditor' ), [] );
        echo '</div>';
        echo '<p style="margin:14px 0 0;color:#50575e;">' . esc_html__( 'Recommended flow: prepare planned AI fix pages, open one in Bricks, run scan, click Fix with AI, review the preview, then Accept or Reject.', 'accessibility-auditor' ) . '</p>';
        echo '</div>';

        echo '<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;margin:18px 0;">';
        echo '<h2 style="margin-top:0;">' . esc_html__( 'Find a Demo', 'accessibility-auditor' ) . '</h2>';
        echo '<p style="margin-top:0;">' . esc_html__( 'Use these filters only if you want a specific demo. Otherwise just prepare planned AI fix pages and open one in Bricks.', 'accessibility-auditor' ) . '</p>';
        echo '<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
        self::render_select( 'strategy', __( 'Demo type', 'accessibility-auditor' ), self::strategy_options( $fixtures ), $filter_values['strategy'] );
        self::render_select( 'scenario', __( 'Demo scenario', 'accessibility-auditor' ), self::scenario_options( $fixtures ), $filter_values['scenario'] );
        echo '<p style="margin:0;"><button type="submit" class="button button-primary">' . esc_html__( 'Show Demos', 'accessibility-auditor' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Reset', 'accessibility-auditor' ) . '</a></p>';
        echo '</form>';
        echo '<details style="margin-top:16px;">';
        echo '<summary style="cursor:pointer;font-weight:600;">' . esc_html__( 'Advanced filters', 'accessibility-auditor' ) . '</summary>';
        echo '<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-top:14px;">';
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
        echo '<p style="margin:0;"><button type="submit" class="button button-primary">' . esc_html__( 'Apply Filters', 'accessibility-auditor' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Reset', 'accessibility-auditor' ) . '</a></p>';
        echo '</form>';
        echo '</details>';
        echo '</div>';

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Demo Page', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'What It Shows', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'accessibility-auditor' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $visible_fixtures as $fixture ) {
            $post = get_page_by_path( $fixture['slug'], OBJECT, 'page' );

            echo '<tr>';
            echo '<td style="width:34%;">';
            echo '<strong>' . esc_html( $fixture['title'] ) . '</strong><br>';
            echo '<code>' . esc_html( $fixture['scenario'] ) . '</code><br>';
            echo '<span style="display:inline-block;margin-top:8px;padding:3px 8px;border-radius:999px;background:#f0f6fc;color:#0a4b78;">' . esc_html( self::strategy_label( (string) $fixture['strategy'] ) ) . '</span>';
            echo '</td>';
            echo '<td>';
            echo '<div><strong>' . esc_html__( 'Rules:', 'accessibility-auditor' ) . '</strong> ' . esc_html( implode( ', ', $fixture['rule_ids'] ) ) . '</div>';
            echo '<div style="margin-top:6px;"><strong>' . esc_html__( 'Bricks components:', 'accessibility-auditor' ) . '</strong> ' . esc_html( implode( ', ', $fixture['components'] ) ) . '</div>';
            echo '<div style="margin-top:8px;color:#50575e;">' . esc_html( $fixture['notes'] ) . '</div>';
            echo '</td>';
            echo '<td>';
            self::render_action_form( __( 'Prepare This Demo', 'accessibility-auditor' ), [ 'scenario' => $fixture['scenario'] ], true );
            if ( $post instanceof \WP_Post ) {
                echo '<div style="margin-top:8px;">';
                echo '<a class="button button-small" href="' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=bricks' ) ) . '">' . esc_html__( 'Open in Bricks', 'accessibility-auditor' ) . '</a>';
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

    private static function strategy_label( string $strategy ): string {
        $labels = [
            'auto-fix'    => __( 'Planned AI Fix', 'accessibility-auditor' ),
            'guided-only' => __( 'Guide-Only Demo', 'accessibility-auditor' ),
        ];

        return $labels[ $strategy ] ?? strtoupper( $strategy );
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
            if ( ( $fixture['strategy'] ?? '' ) === 'flagged' ) {
                return false;
            }
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
            $strategy = (string) $fixture['strategy'];
            if ( $strategy === 'flagged' ) {
                continue;
            }
            $options[ $strategy ] = self::strategy_label( $strategy );
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
