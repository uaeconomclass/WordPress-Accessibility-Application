<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    const OPTION_KEY = 'aa_options';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'registerSettingsPage' ] );
        add_action( 'admin_init', [ __CLASS__, 'registerSettings' ] );
    }

    public static function registerSettings() {
        register_setting( 'aa_settings_group', self::OPTION_KEY, [ __CLASS__, 'sanitizeOptions' ] );

        add_settings_section(
            'aa_settings_api_section',
            __( 'API & Scanner Settings', 'accessibility-auditor' ),
            [ __CLASS__, 'sectionCb' ],
            'aa_settings'
        );

        add_settings_field(
            'claude_use_constant',
            __( 'Use WP-CONFIG constant for Claude key', 'accessibility-auditor' ),
            [ __CLASS__, 'fieldUseConstantCb' ],
            'aa_settings',
            'aa_settings_api_section'
        );

        add_settings_field(
            'claude_api_key',
            __( 'Claude API Key', 'accessibility-auditor' ),
            [ __CLASS__, 'fieldClaudeKeyCb' ],
            'aa_settings',
            'aa_settings_api_section'
        );

        add_settings_field(
            'scanner_endpoint',
            __( 'Scanner endpoint (optional)', 'accessibility-auditor' ),
            [ __CLASS__, 'fieldScannerEndpointCb' ],
            'aa_settings',
            'aa_settings_api_section'
        );

        add_settings_field(
            'compliance_level',
            __( 'WCAG Target Level', 'accessibility-auditor' ),
            [ __CLASS__, 'fieldComplianceLevelCb' ],
            'aa_settings',
            'aa_settings_api_section'
        );

        add_settings_field(
            'use_action_scheduler',
            __( 'Use Action Scheduler (recommended)', 'accessibility-auditor' ),
            [ __CLASS__, 'fieldUseAsCb' ],
            'aa_settings',
            'aa_settings_api_section'
        );
    }

    public static function sanitizeOptions( $input ) {
        $out = [];

        $out['claude_use_constant'] = isset( $input['claude_use_constant'] ) ? (bool) $input['claude_use_constant'] : false;

        if ( empty( $out['claude_use_constant'] ) ) {
            $out['claude_api_key'] = isset( $input['claude_api_key'] ) ? sanitize_text_field( trim( $input['claude_api_key'] ) ) : '';
        } else {
            $prev = get_option( self::OPTION_KEY, [] );
            $out['claude_api_key'] = $prev['claude_api_key'] ?? '';
        }

        $out['scanner_endpoint'] = isset( $input['scanner_endpoint'] ) ? esc_url_raw( $input['scanner_endpoint'] ) : '';
        $out['compliance_level'] = in_array( $input['compliance_level'] ?? '', ['A', 'AA', 'AAA'], true ) ? $input['compliance_level'] : 'AA';
        $out['use_action_scheduler'] = isset( $input['use_action_scheduler'] ) ? (bool) $input['use_action_scheduler'] : false;

        return $out;
    }

    public static function sectionCb() {
        echo '<p>' . esc_html__( 'Configure your AI key and scanner. For security you may prefer to set the Claude API key as a WP-CONFIG constant: AA_CLAUDE_KEY', 'accessibility-auditor' ) . '</p>';
    }

    public static function fieldUseConstantCb() {
        $opts = get_option( self::OPTION_KEY, [] );
        $val = isset( $opts['claude_use_constant'] ) ? (bool) $opts['claude_use_constant'] : false;
        printf( '<input type="checkbox" name="%1$s[claude_use_constant]" value="1" %2$s /> <span class="description">%3$s</span>',
            esc_attr( self::OPTION_KEY ),
            checked( $val, true, false ),
            esc_html__( 'When checked, the plugin will read the Claude key from AA_CLAUDE_KEY constant and will not use the DB-stored key.', 'accessibility-auditor' )
        );
    }

    public static function fieldClaudeKeyCb() {
        $opts = get_option( self::OPTION_KEY, [] );
        $val = isset( $opts['claude_api_key'] ) ? $opts['claude_api_key'] : '';
        $masked = $val ? str_repeat( '•', 8 ) : '';
        printf( '<input type="password" autocomplete="new-password" name="%1$s[claude_api_key]" value="%2$s" class="regular-text" /> <span class="description">%3$s</span>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $val ? $masked : '' ),
            esc_html__( 'Enter the Claude API key. If "Use WP-CONFIG constant" is checked, this field will be ignored.', 'accessibility-auditor' )
        );
    }

    public static function fieldScannerEndpointCb() {
        $opts = get_option( self::OPTION_KEY, [] );
        $val = isset( $opts['scanner_endpoint'] ) ? $opts['scanner_endpoint'] : '';
        printf( '<input type="url" name="%1$s[scanner_endpoint]" value="%2$s" class="regular-text" /> <p class="description">%3$s</p>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $val ),
            esc_html__( 'Optional: URL of remote scanner (e.g. https://scanner.example.com/scan). Leave empty to use local stub.', 'accessibility-auditor' )
        );
    }

    public static function fieldComplianceLevelCb() {
        $opts = get_option( self::OPTION_KEY, [] );
        $val = isset( $opts['compliance_level'] ) ? $opts['compliance_level'] : 'AA';
        printf(
            '<select name="%1$s[compliance_level]">
                <option value="A" %2$s>A</option>
                <option value="AA" %3$s>AA</option>
                <option value="AAA" %4$s>AAA</option>
            </select>
            <p class="description">%5$s</p>',
            esc_attr( self::OPTION_KEY ),
            selected( $val, 'A', false ),
            selected( $val, 'AA', false ),
            selected( $val, 'AAA', false ),
            esc_html__( 'Select target WCAG level for scans and recommendations.', 'accessibility-auditor' )
        );
    }

    public static function fieldUseAsCb() {
        $opts = get_option( self::OPTION_KEY, [] );
        $val = isset( $opts['use_action_scheduler'] ) ? (bool) $opts['use_action_scheduler'] : false;
        printf( '<input type="checkbox" name="%1$s[use_action_scheduler]" value="1" %2$s /> <span class="description">%3$s</span>',
            esc_attr( self::OPTION_KEY ),
            checked( $val, true, false ),
            esc_html__( 'If Action Scheduler is available, use it for background jobs. If unchecked, plugin will use WP-Cron.', 'accessibility-auditor' )
        );
    }

    public static function registerSettingsPage() {
        add_options_page(
            __( 'Accessibility Auditor Settings', 'accessibility-auditor' ),
            __( 'Accessibility Auditor', 'accessibility-auditor' ),
            'manage_options',
            'aa-settings',
            [ __CLASS__, 'renderSettingsPage' ]
        );
    }

    public static function renderSettingsPage() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Accessibility Auditor Settings', 'accessibility-auditor' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'aa_settings_group' );
                do_settings_sections( 'aa_settings' );
                submit_button();
                ?>
            </form>
            <hr/>
            <h2><?php echo esc_html__( 'Runtime info', 'accessibility-auditor' ); ?></h2>
            <p><?php printf( '<strong>%s</strong>: %s', esc_html__( 'Action Scheduler available', 'accessibility-auditor' ), class_exists( 'ActionScheduler' ) ? esc_html__( 'Yes', 'accessibility-auditor' ) : esc_html__( 'No', 'accessibility-auditor' ) ); ?></p>
            <p><?php printf( '<strong>%s</strong>: %s', esc_html__( 'AA_CLAUDE_KEY constant present', 'accessibility-auditor' ), defined( 'AA_CLAUDE_KEY' ) ? esc_html__( 'Yes', 'accessibility-auditor' ) : esc_html__( 'No', 'accessibility-auditor' ) ); ?></p>
        </div>
        <?php
    }

    public static function getClaudeKey() {
        if ( defined( 'AA_CLAUDE_KEY' ) && AA_CLAUDE_KEY ) {
            return AA_CLAUDE_KEY;
        }
        $opts = get_option( self::OPTION_KEY, [] );
        return $opts['claude_api_key'] ?? '';
    }
}
