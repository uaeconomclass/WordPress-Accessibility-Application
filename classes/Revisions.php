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
}
