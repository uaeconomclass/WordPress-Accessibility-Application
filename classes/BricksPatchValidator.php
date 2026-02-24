<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Validates AI-generated Bricks patch payloads before they are applied.
 *
 * Guards against:
 * - Mismatched element IDs
 * - Writes to structural Bricks keys (id, name, parent, children, code)
 * - Excessively large or deeply nested patches
 * - CSS injection via _cssCustom (selector must reference the correct element)
 */
class BricksPatchValidator {

    /**
     * Only these top-level element keys are allowed inside changes/added_keys/removed_keys.
     * AI must stay within `settings` — structural keys are off-limits.
     */
    const ALLOWED_ELEMENT_KEYS = [ 'settings' ];

    /**
     * Settings keys that AI must never touch.
     * `code` = arbitrary code execution risk.
     * `query`/`hasLoop` = structural loop config.
     * `_gridArea`/`_position` = layout structure.
     */
    const BLOCKED_SETTINGS_KEYS = [ 'code', 'query', 'hasLoop', '_gridArea', '_position' ];

    /** Maximum total key count across the entire patch (prevents payload bloat). */
    const MAX_KEYS = 40;

    /** Maximum nesting depth inside a patch section. */
    const MAX_DEPTH = 6;

    /**
     * Validate a decoded patch array before applying it.
     *
     * @param array  $patch               Decoded JSON patch from Claude.
     * @param string $expected_element_id The Bricks element ID we intend to patch.
     *
     * @return string|null  Null = valid. Non-null = error message (skip this patch).
     */
    public static function validate( array $patch, string $expected_element_id ): ?string {

        // 1. element_id must be present and non-empty.
        $patch_id = trim( $patch['element_id'] ?? '' );
        if ( $patch_id === '' ) {
            return 'Patch is missing element_id.';
        }

        // 2. element_id must match the target element we are patching.
        if ( $patch_id !== $expected_element_id ) {
            return "Patch element_id '{$patch_id}' does not match expected '{$expected_element_id}'.";
        }

        // 3. At least one change section must be present.
        $has_content = ! empty( $patch['changes'] )
                    || ! empty( $patch['added_keys'] )
                    || ! empty( $patch['removed_keys'] );
        if ( ! $has_content ) {
            return 'Patch contains no changes, added_keys, or removed_keys.';
        }

        // 4. Validate each change section individually.
        foreach ( [ 'changes', 'added_keys', 'removed_keys' ] as $section ) {
            if ( empty( $patch[ $section ] ) ) {
                continue;
            }
            if ( ! is_array( $patch[ $section ] ) ) {
                return "Patch section '{$section}' must be an object, got scalar.";
            }
            $error = self::validate_section( $patch[ $section ], $section, $expected_element_id );
            if ( $error !== null ) {
                return $error;
            }
        }

        // 5. Reject oversized patches.
        $total_keys = self::count_keys_recursive( $patch );
        if ( $total_keys > self::MAX_KEYS ) {
            return "Patch too large ({$total_keys} keys). Maximum allowed is " . self::MAX_KEYS . '.';
        }

        return null; // All checks passed.
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Validate a single patch section (changes / added_keys / removed_keys).
     * Top-level keys of the section must be in ALLOWED_ELEMENT_KEYS.
     */
    private static function validate_section(
        array  $section,
        string $section_name,
        string $element_id
    ): ?string {
        foreach ( $section as $key => $value ) {
            if ( ! in_array( $key, self::ALLOWED_ELEMENT_KEYS, true ) ) {
                return "Patch section '{$section_name}' contains disallowed key '{$key}'. "
                     . "Only keys: " . implode( ', ', self::ALLOWED_ELEMENT_KEYS ) . " are permitted.";
            }

            if ( $key === 'settings' ) {
                if ( ! is_array( $value ) ) {
                    return "Patch section '{$section_name}.settings' must be an object, got "
                         . gettype( $value ) . ". Claude returned an invalid shape.";
                }
                $error = self::validate_settings( $value, $section_name, $element_id );
                if ( $error !== null ) {
                    return $error;
                }
            }
        }

        return null;
    }

    /**
     * Validate the contents of a `settings` object inside a patch section.
     */
    private static function validate_settings(
        array  $settings,
        string $context,
        string $element_id
    ): ?string {
        foreach ( $settings as $key => $value ) {

            // Block known dangerous keys.
            if ( in_array( $key, self::BLOCKED_SETTINGS_KEYS, true ) ) {
                return "Settings key '{$key}' is blocked in AI patches (safety guard).";
            }

            // _cssCustom: selector must reference this exact element.
            if ( $key === '_cssCustom' && is_string( $value ) ) {
                $error = self::validate_css_custom( $value, $element_id );
                if ( $error !== null ) {
                    return $error;
                }
            }

            // attributes: each attribute name must start with aria-, data-, title, or role.
            if ( $key === 'attributes' && is_array( $value ) ) {
                $error = self::validate_attributes( $value );
                if ( $error !== null ) {
                    return $error;
                }
            }
        }

        return null;
    }

    /**
     * _cssCustom must only reference the target element's selector (#brxe-{id}).
     * Blocks attempts to write selectors for other elements or inject arbitrary CSS.
     */
    private static function validate_css_custom( string $css, string $element_id ): ?string {
        $expected_selector = '#brxe-' . $element_id;

        // Strip comments and check that the only selector block present is the target.
        $stripped = preg_replace( '/\/\*.*?\*\//s', '', $css );

        // Must contain the expected selector at least once.
        if ( strpos( $stripped, $expected_selector ) === false ) {
            return "_cssCustom selector must reference '{$expected_selector}'. "
                 . "Selector for a different element or bare tag not allowed.";
        }

        // Must not contain any selector other than the expected one
        // (catches things like `body { ... }` or `a { ... }` injected alongside the target).
        $selector_pattern = '/[^{}\s][^{]*\{/';
        if ( preg_match_all( $selector_pattern, $stripped, $matches ) ) {
            foreach ( $matches[0] as $found ) {
                $found_selector = trim( rtrim( $found, " \t{" ) );
                if ( strpos( $found_selector, $expected_selector ) === false ) {
                    return "_cssCustom contains unexpected selector '{$found_selector}'. "
                         . "Only '{$expected_selector}' is allowed.";
                }
            }
        }

        return null;
    }

    /**
     * HTML attributes AI may add/change: only aria-*, data-*, role, title, tabindex, alt.
     */
    private static function validate_attributes( array $attributes ): ?string {
        $allowed_prefixes = [ 'aria-', 'data-' ];
        $allowed_exact    = [ 'role', 'title', 'tabindex', 'alt', 'lang' ];

        foreach ( array_keys( $attributes ) as $attr_name ) {
            $allowed = in_array( $attr_name, $allowed_exact, true );
            if ( ! $allowed ) {
                foreach ( $allowed_prefixes as $prefix ) {
                    if ( str_starts_with( $attr_name, $prefix ) ) {
                        $allowed = true;
                        break;
                    }
                }
            }
            if ( ! $allowed ) {
                return "Attribute '{$attr_name}' is not in the allowed attributes list "
                     . "(aria-*, data-*, role, title, tabindex, alt, lang).";
            }
        }

        return null;
    }

    /**
     * Recursively count total keys to detect payload bloat.
     */
    private static function count_keys_recursive( array $arr, int $depth = 0 ): int {
        if ( $depth > self::MAX_DEPTH ) {
            return self::MAX_KEYS + 1; // force rejection
        }
        $count = count( $arr );
        foreach ( $arr as $value ) {
            if ( is_array( $value ) ) {
                $count += self::count_keys_recursive( $value, $depth + 1 );
            }
        }
        return $count;
    }
}
