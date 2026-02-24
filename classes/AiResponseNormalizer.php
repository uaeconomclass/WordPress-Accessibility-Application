<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Normalizes raw Claude API response text into a clean JSON string.
 *
 * Claude sometimes wraps JSON in markdown fences, adds trailing commas,
 * includes invisible control characters, or returns incomplete brackets.
 * This class handles all of that cleanup before the caller attempts JSON decode.
 */
class AiResponseNormalizer {

    /**
     * Normalize a raw Claude response to a clean JSON string.
     *
     * @param mixed $response Raw value returned by ClaudeClient::request() or WP_Error.
     * @return string         Cleaned JSON string, ready for json_decode().
     * @throws \Exception     If $response is a WP_Error.
     */
    public static function normalize( $response ): string {
        // Unwrap WP_Error — callers should have checked is_wp_error() first,
        // but throw here as a safety net so silent failures become visible.
        if ( is_wp_error( $response ) ) {
            throw new \Exception( 'Claude API request failed: ' . $response->get_error_message() );
        }

        // Normalize non-string types to a string.
        if ( is_array( $response ) ) {
            $response = $response['content'] ?? $response['body'] ?? $response['message'] ?? wp_json_encode( $response );
        } elseif ( is_object( $response ) ) {
            $response = $response->content ?? $response->body ?? $response->message ?? json_encode( $response );
        }

        if ( ! is_string( $response ) ) {
            $response = wp_json_encode( $response );
        }

        // Strip invisible control characters (keep newlines/tabs for readability).
        $response = mb_convert_encoding( $response, 'UTF-8', 'UTF-8' );
        $response = preg_replace( '/[[:cntrl:]&&[^\r\n\t]]/', '', $response );

        // Remove markdown code fences Claude occasionally wraps JSON in.
        $response = preg_replace( '/^```(?:json)?\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/', '', $response );

        // Remove trailing commas before closing brackets (common Claude artifact).
        $response = preg_replace( '/,(\s*[\]\}])/', '$1', $response );

        $response = trim( $response );

        // Close unclosed top-level brackets (Claude truncation artifact).
        if ( str_starts_with( $response, '[' ) && ! str_ends_with( $response, ']' ) ) {
            $response .= ']';
        } elseif ( str_starts_with( $response, '{' ) && ! str_ends_with( $response, '}' ) ) {
            $response .= '}';
        }

        // Attempt decode to verify — if it fails, try extracting a valid JSON substring.
        if ( json_decode( $response ) === null && json_last_error() !== JSON_ERROR_NONE ) {
            error_log( '[AA:AiResponseNormalizer] Initial decode failed: ' . json_last_error_msg() );
            error_log( '[AA:AiResponseNormalizer] Raw (500 chars): ' . substr( $response, 0, 500 ) );

            if ( preg_match( '/\{.*\}|\[.*\]/s', $response, $match ) ) {
                $response = $match[0];
            }
        }

        // Re-encode through PHP to guarantee consistent formatting.
        $decoded = json_decode( $response, true );
        if ( $decoded !== null ) {
            return wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        }

        // Return whatever we have — json_decode in the caller will handle the error.
        return $response;
    }
}
