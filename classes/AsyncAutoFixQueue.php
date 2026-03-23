<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AsyncAutoFixQueue {
    const HOOK = 'aa_process_auto_fix_job';
    const META_PREFIX = '_aa_autofix_job_';
    const AJAX_ACTION = 'aa_process_autofix_job';

    public static function init() {
        add_action( self::HOOK, [ __CLASS__, 'process_job' ], 10, 1 );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ __CLASS__, 'handle_loopback_request' ] );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ __CLASS__, 'handle_loopback_request' ] );
    }

    public static function enqueue( array $payload ): array {
        $job_id  = self::generate_job_id();
        $post_id = absint( $payload['post_id'] ?? 0 );

        $job = [
            'job_id'      => $job_id,
            'post_id'     => $post_id,
            'status'      => 'queued',
            'created_at'  => current_time( 'mysql' ),
            'created_at_ts' => time(),
            'updated_at'  => current_time( 'mysql' ),
            'updated_at_ts' => time(),
            'payload'     => $payload,
            'result'      => null,
            'error_code'  => '',
            'error_message' => '',
            'attempts'    => 0,
            'dispatch_token' => wp_generate_password( 20, false, false ),
        ];

        self::save_job( $job );
        self::schedule_job( $job_id );
        self::dispatch_loopback_job( $job );

        return $job;
    }

    public static function get_job( string $job_id ): ?array {
        if ( $job_id === '' ) {
            return null;
        }

        $raw = get_option( self::option_key( $job_id ), null );
        if ( ! is_array( $raw ) ) {
            return null;
        }

        return $raw;
    }

    public static function save_job( array $job ): void {
        $job['updated_at'] = current_time( 'mysql' );
        $job['updated_at_ts'] = time();
        update_option( self::option_key( (string) $job['job_id'] ), $job, false );
    }

    public static function process_job( string $job_id ): void {
        $job = self::get_job( $job_id );
        if ( ! is_array( $job ) ) {
            return;
        }

        if ( in_array( $job['status'] ?? '', [ 'ready', 'error' ], true ) ) {
            return;
        }

        $job['status']   = 'processing';
        $job['attempts'] = (int) ( $job['attempts'] ?? 0 ) + 1;
        self::save_job( $job );

        $result = AI::instance()->process_auto_fix_payload( is_array( $job['payload'] ?? null ) ? $job['payload'] : [] );

        if ( is_wp_error( $result ) ) {
            $job['status']        = 'error';
            $job['error_code']    = (string) $result->get_error_code();
            $job['error_message'] = (string) $result->get_error_message();
            $job['result']        = null;
            self::save_job( $job );
            return;
        }

        $job['status']         = 'ready';
        $job['error_code']     = '';
        $job['error_message']  = '';
        $job['result']         = $result;
        self::save_job( $job );
    }

    public static function handle_loopback_request(): void {
        $job_id = sanitize_text_field( (string) ( $_REQUEST['job_id'] ?? '' ) );
        $token  = sanitize_text_field( (string) ( $_REQUEST['dispatch_token'] ?? '' ) );
        $job    = self::get_job( $job_id );

        if ( ! is_array( $job ) || $job_id === '' || $token === '' || ! hash_equals( (string) ( $job['dispatch_token'] ?? '' ), $token ) ) {
            wp_send_json_error( [ 'message' => 'Invalid auto-fix loopback request.' ], 403 );
        }

        self::process_job( $job_id );
        wp_send_json_success( [ 'job_id' => $job_id ] );
    }

    public static function redispatch_if_stalled( string $job_id ): void {
        $job = self::get_job( $job_id );
        if ( ! is_array( $job ) ) {
            return;
        }

        if ( in_array( $job['status'] ?? '', [ 'ready', 'error', 'processing' ], true ) ) {
            return;
        }

        self::dispatch_loopback_job( $job );
    }

    private static function schedule_job( string $job_id ): void {
        $timestamp = time() + 1;

        if ( self::should_use_action_scheduler() && function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( $timestamp, self::HOOK, [ $job_id ], 'accessibility-auditor' );
            return;
        }

        if ( ! wp_next_scheduled( self::HOOK, [ $job_id ] ) ) {
            wp_schedule_single_event( $timestamp, self::HOOK, [ $job_id ] );
        }
    }

    private static function dispatch_loopback_job( array $job ): void {
        $job_id = (string) ( $job['job_id'] ?? '' );
        $token  = (string) ( $job['dispatch_token'] ?? '' );
        if ( $job_id === '' || $token === '' ) {
            return;
        }

        wp_remote_post( admin_url( 'admin-ajax.php' ), [
            'timeout'  => 0.01,
            'blocking' => false,
            'body'     => [
                'action'         => self::AJAX_ACTION,
                'job_id'         => $job_id,
                'dispatch_token' => $token,
            ],
        ] );
    }

    private static function should_use_action_scheduler(): bool {
        $opts = get_option( Settings::OPTION_KEY, [] );
        $enabled = ! empty( $opts['use_action_scheduler'] );
        return $enabled && class_exists( 'ActionScheduler' );
    }

    private static function option_key( string $job_id ): string {
        return self::META_PREFIX . sanitize_key( $job_id );
    }

    private static function generate_job_id(): string {
        return 'job_' . wp_generate_password( 12, false, false ) . '_' . time();
    }
}
