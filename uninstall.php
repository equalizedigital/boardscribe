<?php
/**
 * Runs when the plugin is deleted from the WordPress plugins screen.
 *
 * Only removes data when the user has explicitly opted in via
 * Meeting Minutes → Settings → Delete Data on Uninstall.
 * This prevents accidental data loss during deactivate/reactivate cycles.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$edmm_settings = get_option( 'edmm_settings', [] );

if ( empty( $edmm_settings['delete_on_uninstall'] ) ) {
	return;
}

// Delete all meeting minutes posts and their associated meta.
$edmm_meeting_minutes_post_ids = get_posts(
	[
		'post_type'      => 'edmm_meeting_minutes',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]
);

foreach ( $edmm_meeting_minutes_post_ids as $edmm_meeting_minutes_post_id ) {
	wp_delete_post( $edmm_meeting_minutes_post_id, true );
}

// Delete plugin settings.
delete_option( 'edmm_settings' );
