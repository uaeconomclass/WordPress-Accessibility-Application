<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utilities for locating and updating elements in a Bricks Builder element tree.
 *
 * The Bricks element tree is a flat-ish JSON array where each element may have
 * a `children` array of nested elements. All methods recurse into children.
 */
class BricksElementFinder {

    /**
     * Find a single Bricks element by its ID (recursive).
     *
     * @param array  $elements Bricks element tree (top-level array).
     * @param string $id       Element ID to search for.
     * @return array|null      The element array, or null if not found.
     */
    public static function find( array $elements, string $id ): ?array {
        foreach ( $elements as $el ) {
            if ( ( $el['id'] ?? null ) === $id ) {
                return $el;
            }
            if ( ! empty( $el['children'] ) ) {
                $found = self::find( $el['children'], $id );
                if ( $found !== null ) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Update a Bricks element in-place within the tree (recursive, modifies by reference).
     *
     * @param array  $elements  Bricks element tree (modified by reference).
     * @param string $id        Element ID to update.
     * @param array  $new_data  Replacement element data.
     * @return bool             True if the element was found and updated.
     */
    public static function update( array &$elements, string $id, array $new_data ): bool {
        foreach ( $elements as &$el ) {
            if ( ( $el['id'] ?? null ) === $id ) {
                $el = $new_data;
                return true;
            }
            if ( ! empty( $el['children'] ) ) {
                if ( self::update( $el['children'], $id, $new_data ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Resolve the Bricks elements affected by a given axe-core issue.
     *
     * Uses a 3-level fallback strategy:
     *  1. `#brxe-{id}` found in axe node.target selector strings (normal case).
     *  2. `id="brxe-{id}"` attribute present in axe node.html (bare-tag selectors like `pre`, `a`).
     *  3. axe node.html content matched against element settings.text (inline HTML inside text-basic).
     *     Note: axe injects `style="outline:..."` during scanning — stripped before comparison.
     *
     * @param array $elements Bricks element tree.
     * @param array $issue    A single axe-core issue (violations[n]).
     * @return array          Array of matching Bricks element arrays (may be empty).
     */
    public static function from_issue( array $elements, array $issue ): array {
        $target_ids = [];

        foreach ( $issue['nodes'] ?? [] as $node ) {
            // Level 1: #brxe-{id} in target selector strings.
            foreach ( $node['target'] ?? [] as $selector ) {
                if ( preg_match( '/#brxe-([\w-]+)/i', $selector, $m ) ) {
                    $target_ids[] = $m[1];
                }
            }
            // Level 2: id="brxe-{id}" attribute in node.html.
            if ( empty( $target_ids ) && ! empty( $node['html'] ) ) {
                if ( preg_match( '/\bid="brxe-([\w-]+)"/i', $node['html'], $m ) ) {
                    $target_ids[] = $m[1];
                    error_log( '[AA:BricksElementFinder] id-attr fallback matched: ' . $m[1] );
                }
            }
        }

        // Resolve IDs → full element arrays.
        $result = [];
        foreach ( array_unique( $target_ids ) as $id ) {
            $el = self::find( $elements, $id );
            if ( $el !== null ) {
                $result[] = $el;
            }
        }

        // Level 3: HTML content match against settings.text (no ID found in selectors at all).
        if ( empty( $result ) ) {
            error_log( sprintf(
                '[AA:BricksElementFinder] content-match fallback for issue=%s nodes=%d',
                $issue['id'] ?? '?',
                count( $issue['nodes'] ?? [] )
            ) );

            $needles = [];
            foreach ( $issue['nodes'] ?? [] as $node ) {
                if ( ! empty( $node['html'] ) ) {
                    // Strip axe-injected style attributes before comparing.
                    $clean     = preg_replace( '/\s+style="[^"]*"/', '', $node['html'] );
                    $needles[] = trim( $clean );
                }
            }

            foreach ( $elements as $el ) {
                $text = trim( $el['settings']['text'] ?? '' );
                if ( ! $text ) {
                    continue;
                }
                foreach ( $needles as $needle ) {
                    if ( $needle !== '' && strpos( $text, $needle ) !== false ) {
                        $result[] = $el;
                        break;
                    }
                }
            }

            if ( ! empty( $result ) ) {
                error_log( sprintf(
                    '[AA:BricksElementFinder] content-match found %d element(s) for issue=%s',
                    count( $result ),
                    $issue['id'] ?? '?'
                ) );
            }
        }

        return $result;
    }
}
