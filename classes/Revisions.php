<?php
namespace Accessibility_Auditor;

if ( ! defined( 'ABSPATH' ) ) exit;

class Revisions {
    public static function get_history( $post_id ) {
        $history = get_post_meta( $post_id, '_aa_autofix_history', true );
        return is_array( $history ) ? $history : [];
    }

    public static function log_autofix( $post_id, $fixes ) {
        $history = get_post_meta( $post_id, '_aa_autofix_history', true );
        if ( ! is_array( $history ) ) $history = [];
        $history[] = [ 'time' => current_time( 'mysql' ), 'fixes' => $fixes, 'user' => get_current_user_id() ];
        update_post_meta( $post_id, '_aa_autofix_history', $history );
    }

    /**
     * Save a snapshot of the current Bricks element tree for rollback.
     * Returns the meta key under which the snapshot is stored.
     *
     * @param int    $post_id  WordPress post ID.
     * @param array  $elements Bricks element tree at the time of snapshot.
     * @param string $context  Short label (e.g. 'pre_fix_backup', 'ai_fix').
     * @return string          Meta key of the stored revision.
     */
    public static function save_bricks_snapshot( int $post_id, array $elements, string $context = 'auto_fix' ): string {
        $key = "bricks_revision_{$context}_" . time();

        update_post_meta( $post_id, $key, wp_json_encode( [
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
            'context'   => $context,
            'elements'  => $elements,
        ] ) );

        return $key;
    }

    /**
     * Generate a human-readable changelog array from the list of applied elements.
     *
     * @param array $applied Bricks elements that were patched.
     * @return string[]      Array of changelog lines.
     */
    public static function generate_changelog( array $applied ): array {
        $log = [];
        foreach ( $applied as $el ) {
            $id      = $el['id'] ?? 'unknown';
            $changes = $el['changes']['settings'] ?? [];
            foreach ( $changes as $key => $val ) {
                $display = is_array( $val ) ? wp_json_encode( $val ) : (string) $val;
                $log[] = sprintf( 'Element %s: updated settings.%s → %s', $id, $key, $display );
            }
            if ( empty( $changes ) ) {
                $log[] = sprintf( 'Element %s: patch applied', $id );
            }
        }
        return $log;
    }
}
