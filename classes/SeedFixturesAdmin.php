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

        $notice   = null;
        $fixtures = aa_seed_fixture_catalog();

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $notice = self::handle_post( $fixtures );
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

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Scenario', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Rules', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Components', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Strategy', 'accessibility-auditor' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'accessibility-auditor' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $fixtures as $fixture ) {
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
        ] );

        $selected = array_values( array_filter( $fixtures, static function( array $fixture ) use ( $filters ): bool {
            if ( ! empty( $filters['scenario'] ) && $fixture['scenario'] !== $filters['scenario'] ) {
                return false;
            }
            if ( ! empty( $filters['strategy'] ) && $fixture['strategy'] !== $filters['strategy'] ) {
                return false;
            }
            return true;
        } ) );

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
}
