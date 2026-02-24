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
        $api_key = Settings::getClaudeKey();

        if ( empty( $api_key ) ) {
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
            return new \WP_Error( 'api_request_failed', $response->get_error_message() );
        }

        $status   = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );

        if ( $status < 200 || $status >= 300 ) {
            return new \WP_Error( 'api_http_error', "HTTP {$status}: " . substr( $raw_body, 0, 200 ) );
        }

        $decoded = json_decode( $raw_body, true );
        if ( $decoded === null ) {
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
            return new \WP_Error( 'api_empty_response', 'API responded but content is empty.' );
        }

        return $content;
    }
}
