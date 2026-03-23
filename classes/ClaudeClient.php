<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HTTP client for the Anthropic Claude API.
 *
 * Encapsulates all communication with api.anthropic.com/v1/messages.
 * Returns the raw text content string on success, or WP_Error on failure.
 */
class ClaudeClient {

    const API_URL      = 'https://api.anthropic.com/v1/messages';
    const MODEL        = 'claude-sonnet-4-20250514';
    const MAX_TOKENS   = 4096;
    const TIMEOUT      = 300;
    const API_VERSION  = '2023-06-01';

    /**
     * Send a prompt to Claude and return the response text.
     *
     * @param string $prompt    The user prompt to send.
     * @param bool   $json_mode When true, adds a system instruction to respond only with valid JSON.
     * @return string|\WP_Error Response text on success, WP_Error on failure.
     */
    public static function request( string $prompt, bool $json_mode = false ) {
        $result = self::request_with_meta( $prompt, $json_mode );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result['content'] ?? '';
    }

    /**
     * Send a prompt to Claude and return structured metadata for auditing.
     *
     * @param string $prompt
     * @param bool   $json_mode
     * @param array  $audit_context
     * @return array|\WP_Error
     */
    public static function request_with_meta( string $prompt, bool $json_mode = false, array $audit_context = [] ) {
        $api_key = Settings::getClaudeKey();
        if ( empty( $api_key ) ) {
            $audit_id = LlmAuditLogger::start_call( array_merge( $audit_context, [
                'model'  => self::MODEL,
                'prompt' => $prompt,
                'status' => 'started',
            ] ) );
            LlmAuditLogger::finish_call( $audit_id, [
                'status'     => 'error',
                'error_code' => 'no_api_key',
                'latency_ms' => 0,
            ] );
            return new \WP_Error( 'no_api_key', 'Claude API key is not configured.' );
        }

        $body = [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ];

        if ( $json_mode ) {
            $body['system'] = 'Respond ONLY with valid JSON.';
        }

        $audit_id = LlmAuditLogger::start_call( array_merge( $audit_context, [
            'model'           => self::MODEL,
            'prompt'          => $prompt,
            'request_payload' => [
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'anthropic-version' => self::API_VERSION,
                    'x-api-key'         => '[redacted]',
                ],
                'body'    => $body,
            ],
            'status'          => 'started',
        ] ) );
        $started_at = microtime( true );

        $response = wp_remote_post( self::API_URL, [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => self::API_VERSION,
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => self::TIMEOUT,
        ] );

        if ( is_wp_error( $response ) ) {
            LlmAuditLogger::finish_call( $audit_id, [
                'status'     => 'error',
                'error_code' => 'api_request_failed',
                'latency_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
                'response_payload' => [
                    'error_message' => $response->get_error_message(),
                    'error_data'    => $response->get_error_data(),
                ],
            ] );
            return new \WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        if ( $status < 200 || $status >= 300 ) {
            LlmAuditLogger::finish_call( $audit_id, [
                'status'     => 'error',
                'error_code' => 'api_http_error',
                'latency_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
                'response_payload' => $raw_body,
            ] );
            return new \WP_Error( 'api_http_error', "HTTP {$status}: " . substr( $raw_body, 0, 200 ) );
        }

        $decoded = json_decode( $raw_body, true );
        if ( $decoded === null ) {
            LlmAuditLogger::finish_call( $audit_id, [
                'status'     => 'error',
                'error_code' => 'api_json_decode_error',
                'latency_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
                'response_payload' => $raw_body,
            ] );
            return new \WP_Error( 'api_json_decode_error', 'Failed to decode API response: ' . substr( $raw_body, 0, 200 ) );
        }

        // Claude v1/messages response: content is an array of text blocks.
        $content = '';
        if ( isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
            foreach ( $decoded['content'] as $block ) {
                if ( isset( $block['text'] ) ) {
                    $content .= $block['text'];
                }
            }
        }

        $content = trim( $content );

        if ( $content === '' ) {
            LlmAuditLogger::finish_call( $audit_id, [
                'status'     => 'error',
                'error_code' => 'api_empty_response',
                'latency_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
                'response_payload' => $decoded,
            ] );
            return new \WP_Error( 'api_empty_response', 'API responded but content is empty.' );
        }

        $usage = is_array( $decoded['usage'] ?? null ) ? $decoded['usage'] : [];

        $result = [
            'content'       => $content,
            'model'         => (string) ( $decoded['model'] ?? self::MODEL ),
            'stop_reason'   => (string) ( $decoded['stop_reason'] ?? '' ),
            'input_tokens'  => (int) ( $usage['input_tokens'] ?? 0 ),
            'output_tokens' => (int) ( $usage['output_tokens'] ?? 0 ),
            'latency_ms'    => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
            'response_payload' => $decoded,
            'audit_id'      => $audit_id,
        ];

        LlmAuditLogger::finish_call( $audit_id, array_merge( $result, [ 'status' => 'success' ] ) );

        return $result;
    }
}
