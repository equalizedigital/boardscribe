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
	$edbs_meeting_post_id = (int) $edbs_meeting_post_id;

	// wp_delete_post() only clears term relationships for taxonomies
	// registered on this post type at the moment it runs
	// (get_object_taxonomies( $post_type )) - a Pro-registered taxonomy
	// (meeting category/type) isn't registered during a plain
	// WP_UNINSTALL_PLUGIN run of this (free) plugin even with Pro still
	// active, since Pro only registers its taxonomies via the free
	// plugin's own edbs_after_register_cpt action, which never fires
	// here. Without this, those relationships (and their term counts)
	// would silently survive the delete. Read and clean these up
	// directly, by object ID rather than by taxonomy name, so this stays
	// correct without this (free) plugin needing to know what Pro's
	// taxonomies are called.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup, no taxonomy registered to clean these up through the term-relationship API.
	$edbs_term_taxonomy_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d", $edbs_meeting_post_id ) );

	wp_delete_post( $edbs_meeting_post_id, true );

	if ( ! $edbs_term_taxonomy_ids ) {
		continue;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup; wp_delete_post() above never touched these rows since no taxonomy was registered for this post type.
	$wpdb->delete( $wpdb->term_relationships, [ 'object_id' => $edbs_meeting_post_id ] );

	foreach ( $edbs_term_taxonomy_ids as $edbs_term_taxonomy_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup; wp_update_term_count_now() needs the owning taxonomy registered, which it isn't here.
		$edbs_term_row = $wpdb->get_row( $wpdb->prepare( "SELECT term_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $edbs_term_taxonomy_id ) );
		if ( ! $edbs_term_row ) {
			continue;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time uninstall cleanup; wp_update_term_count_now() needs the owning taxonomy registered, which it isn't here.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->term_taxonomy} SET count = GREATEST( count - 1, 0 ) WHERE term_taxonomy_id = %d", $edbs_term_taxonomy_id ) );

		// The raw UPDATE above bypasses core's term object cache - without
		// this, get_term()/get_terms() keep serving the pre-decrement
		// count until something else happens to invalidate it. Doesn't
		// require the taxonomy to be registered, just its slug (read
		// above alongside term_id).
		clean_term_cache( [ (int) $edbs_term_row->term_id ], $edbs_term_row->taxonomy );
	}
}

// Delete plugin settings.
delete_option( 'edbs_settings' );
delete_option( 'edbs_activation_date' );
