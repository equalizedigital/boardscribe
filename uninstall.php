<?php
/**
 * Runs when the plugin is deleted from the WordPress plugins screen.
 *
 * Only removes data when the user has explicitly opted in via
 * BoardScribe → Settings → Delete Data on Uninstall.
 * This prevents accidental data loss during deactivate/reactivate cycles.
 *
 * @package EqualizeDigital\BoardScribe
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$edbs_settings = get_option( 'edbs_settings', [] );

if ( empty( $edbs_settings['delete_on_uninstall'] ) ) {
	return;
}

// Delete every BoardScribe meeting post and its associated meta, regardless
// of status. get_posts()'s post_status => 'any' deliberately excludes
// statuses core marks exclude_from_search (trash, auto-draft) - the wrong
// choice here, since a user opting into full data deletion wants trashed
// and never-finished-autosave meetings gone too, not left behind as
// orphaned posts + postmeta. A direct $wpdb query also doesn't depend on
// edbs_meeting (or any Pro-registered custom meeting status) being a
// registered post type at uninstall time, unlike get_posts()/WP_Query.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup, no post type registered to query through WP_Query.
$edbs_meeting_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'edbs_meeting' ) );

foreach ( $edbs_meeting_post_ids as $edbs_meeting_post_id ) {
	wp_delete_post( (int) $edbs_meeting_post_id, true );
}

// Delete plugin settings.
delete_option( 'edbs_settings' );
delete_option( 'edbs_activation_date' );
