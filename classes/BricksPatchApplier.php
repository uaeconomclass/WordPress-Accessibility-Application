<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Applies a validated AI patch to a Bricks Builder element array.
 *
 * Patch format (decoded JSON from Claude):
 * {
 *   "element_id": "abc123",
 *   "changes":      { "settings": { ... } },   // deep-merge into element
 *   "added_keys":   { "settings": { ... } },   // deep-merge (same as changes)
 *   "removed_keys": { "settings": { ... } }    // deep-unset matching keys
 * }
 *
 * Assumes the patch has already been validated by BricksPatchValidator.
 */
class BricksPatchApplier {

    /**
     * Apply a patch to a Bricks element and return the modified copy.
     *
     * @param array        $element Original Bricks element array.
     * @param array|string $patch   Decoded patch array (or JSON string).
     * @return array                Modified element.
     * @throws \InvalidArgumentException If patch is not a valid array.
     */
    public static function apply( array $element, $patch ): array {
        if ( is_string( $patch ) ) {
            $patch = json_decode( $patch, true );
        }
        if ( ! is_array( $patch ) ) {
            throw new \InvalidArgumentException( 'BricksPatchApplier: patch must be an array or JSON string.' );
        }

        // Apply "changes" — update existing settings values.
        if ( ! empty( $patch['changes'] ) && is_array( $patch['changes'] ) ) {
            $element = self::deep_merge( $element, $patch['changes'] );
        }

        // Apply "added_keys" — add new settings keys (same merge semantics as changes).
        if ( ! empty( $patch['added_keys'] ) && is_array( $patch['added_keys'] ) ) {
            $element = self::deep_merge( $element, $patch['added_keys'] );
        }

        // Apply "removed_keys" — unset matching keys.
        if ( ! empty( $patch['removed_keys'] ) && is_array( $patch['removed_keys'] ) ) {
            $element = self::deep_remove( $element, $patch['removed_keys'] );
        }

        // Preserve element ID if AI omitted it from the patched data.
        if ( empty( $element['id'] ) && ! empty( $patch['element_id'] ) ) {
            $element['id'] = $patch['element_id'];
        }

        // Ensure children remains a valid array after merge.
        if ( isset( $element['children'] ) && ! is_array( $element['children'] ) ) {
            $element['children'] = [];
        }

        return $element;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively merge $patch into $original.
     * Nested arrays are merged; scalar values are overwritten.
     */
    private static function deep_merge( array $original, array $patch ): array {
        foreach ( $patch as $key => $value ) {
            if ( is_array( $value ) && isset( $original[ $key ] ) && is_array( $original[ $key ] ) ) {
                $original[ $key ] = self::deep_merge( $original[ $key ], $value );
            } else {
                $original[ $key ] = $value;
            }
        }
        // Keep Bricks children structure valid after any merge.
        if ( isset( $original['children'] ) && ! is_array( $original['children'] ) ) {
            $original['children'] = [];
        }
        return $original;
    }

    /**
     * Recursively unset keys from $original based on $patch.
     * A value of `true` in $patch means "remove this key entirely".
     * A nested array means "recurse into this key".
     */
    private static function deep_remove( array $original, array $patch ): array {
        foreach ( $patch as $key => $value ) {
            if ( ! isset( $original[ $key ] ) ) {
                continue;
            }
            if ( $value === true ) {
                unset( $original[ $key ] );
            } elseif ( is_array( $value ) && is_array( $original[ $key ] ) ) {
                $original[ $key ] = self::deep_remove( $original[ $key ], $value );
            }
        }
        return $original;
    }
}
