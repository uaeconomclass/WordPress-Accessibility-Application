<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LlmAuditLogger {
    const TABLE_SUFFIX = 'aa_llm_calls';

    public static function install() {
        global $wpdb;

        $table             = self::table_name();
        $charset_collate   = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            trace_id VARCHAR(64) DEFAULT '',
            call_mode VARCHAR(32) DEFAULT '',
            post_id BIGINT UNSIGNED DEFAULT 0,
            rule_id VARCHAR(64) DEFAULT '',
            component VARCHAR(64) DEFAULT '',
            model VARCHAR(64) DEFAULT '',
            prompt_version VARCHAR(32) DEFAULT '',
            status VARCHAR(32) DEFAULT '',
            error_code VARCHAR(64) DEFAULT '',
            prompt_chars INT UNSIGNED DEFAULT 0,
            response_chars INT UNSIGNED DEFAULT 0,
            input_tokens INT UNSIGNED DEFAULT 0,
            output_tokens INT UNSIGNED DEFAULT 0,
            estimated_cost_usd DECIMAL(10,6) DEFAULT 0,
            latency_ms INT UNSIGNED DEFAULT 0,
            prompt_preview TEXT NULL,
            response_preview TEXT NULL,
            request_payload LONGTEXT NULL,
            response_payload LONGTEXT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY trace_id (trace_id),
            KEY post_id (post_id),
            KEY call_mode (call_mode),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function start_call( array $context ): int {
        global $wpdb;

        $wpdb->insert(
            self::table_name(),
            [
                'trace_id'        => sanitize_text_field( (string) ( $context['trace_id'] ?? '' ) ),
                'call_mode'       => sanitize_key( (string) ( $context['call_mode'] ?? '' ) ),
                'post_id'         => absint( $context['post_id'] ?? 0 ),
                'rule_id'         => sanitize_key( (string) ( $context['rule_id'] ?? '' ) ),
                'component'       => sanitize_key( (string) ( $context['component'] ?? '' ) ),
                'model'           => sanitize_text_field( (string) ( $context['model'] ?? '' ) ),
                'prompt_version'  => sanitize_text_field( (string) ( $context['prompt_version'] ?? '' ) ),
                'status'          => sanitize_key( (string) ( $context['status'] ?? 'started' ) ),
                'prompt_chars'    => strlen( (string) ( $context['prompt'] ?? '' ) ),
                'prompt_preview'  => self::preview( (string) ( $context['prompt'] ?? '' ) ),
                'request_payload' => self::format_payload( $context['request_payload'] ?? ( $context['prompt'] ?? '' ) ),
                'started_at'      => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    public static function finish_call( int $audit_id, array $result ): void {
        if ( $audit_id <= 0 ) {
            return;
        }

        global $wpdb;

        $wpdb->update(
            self::table_name(),
            [
                'status'             => sanitize_key( (string) ( $result['status'] ?? 'completed' ) ),
                'error_code'         => sanitize_key( (string) ( $result['error_code'] ?? '' ) ),
                'response_chars'     => strlen( (string) ( $result['content'] ?? '' ) ),
                'input_tokens'       => absint( $result['input_tokens'] ?? 0 ),
                'output_tokens'      => absint( $result['output_tokens'] ?? 0 ),
                'estimated_cost_usd' => self::estimate_cost_usd(
                    (string) ( $result['model'] ?? '' ),
                    (int) ( $result['input_tokens'] ?? 0 ),
                    (int) ( $result['output_tokens'] ?? 0 )
                ),
                'latency_ms'         => absint( $result['latency_ms'] ?? 0 ),
                'response_preview'   => self::preview( (string) ( $result['content'] ?? '' ) ),
                'response_payload'   => self::format_payload( $result['response_payload'] ?? ( $result['content'] ?? '' ) ),
                'finished_at'        => current_time( 'mysql' ),
            ],
            [ 'id' => $audit_id ],
            [ '%s', '%s', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    private static function preview( string $value, int $limit = 1000 ): string {
        $value = trim( preg_replace( '/\s+/', ' ', $value ) ?? '' );
        return mb_substr( $value, 0, $limit );
    }

    private static function format_payload( $payload ): string {
        if ( is_array( $payload ) || is_object( $payload ) ) {
            $json = wp_json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            return is_string( $json ) ? $json : '';
        }

        $payload = trim( (string) $payload );
        if ( $payload === '' ) {
            return '';
        }

        $decoded = json_decode( $payload, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $json = wp_json_encode(
                $decoded,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            return is_string( $json ) ? $json : $payload;
        }

        return $payload;
    }

    private static function estimate_cost_usd( string $model, int $input_tokens, int $output_tokens ): float {
        $rates = [
            'claude-sonnet-4-20250514' => [ 'input' => 3.00, 'output' => 15.00 ],
        ];

        $pricing = $rates[ $model ] ?? $rates['claude-sonnet-4-20250514'];
        $input_cost  = ( $input_tokens / 1000000 ) * $pricing['input'];
        $output_cost = ( $output_tokens / 1000000 ) * $pricing['output'];

        return round( $input_cost + $output_cost, 6 );
    }
}
