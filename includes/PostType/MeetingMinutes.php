<?php
/**
 * Meeting Minutes custom post type registration.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and manages the edmm_meeting_minutes custom post type.
 */
class MeetingMinutes {

	/**
	 * Hooks the CPT registration into WordPress init.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Registers the edmm_meeting_minutes custom post type.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'           => _x( 'Meeting Minutes', 'Post Type General Name', 'edmm' ),
			'singular_name'  => _x( 'Meeting Minute', 'Post Type Singular Name', 'edmm' ),
			'menu_name'      => __( 'Meeting Minutes', 'edmm' ),
			'name_admin_bar' => __( 'Meeting Minute', 'edmm' ),
		];

		$args = [
			'label'         => __( 'Meeting Minute', 'edmm' ),
			'labels'        => $labels,
			'supports'      => [ 'title' ],
			// No public single/archive templates — meeting minutes are only ever
			// displayed via the [edmm_meeting_minutes] shortcode's own REST endpoint.
			'public'        => false,
			'rewrite'       => false,
			'has_archive'   => false,
			'show_ui'       => true,
			// Intentionally still exposes the default /wp/v2/edmm_meeting_minutes
			// REST route (needed for the block editor meta box UI); this is fine
			// since published meeting minutes are meant to be public records.
			'show_in_rest'  => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-calendar',
		];

		/**
		 * Filters the edmm_meeting_minutes CPT registration args. Pro plugin uses
		 * this to enable public/rewrite/archive support or a custom capability_type.
		 *
		 * @since x.x.x
		 *
		 * @param array $args The register_post_type() args.
		 */
		$args = apply_filters( 'edmm_cpt_args', $args );

		/**
		 * Fires before the Meeting Minutes CPT is registered. Register taxonomies
		 * that need to bind to the CPT here.
		 *
		 * @since x.x.x
		 */
		do_action( 'edmm_before_register_cpt' );

		register_post_type( 'edmm_meeting_minutes', $args );

		/**
		 * Fires after the Meeting Minutes CPT is registered. Register taxonomies
		 * or rewrite rules here.
		 *
		 * @since x.x.x
		 */
		do_action( 'edmm_after_register_cpt' );
	}
}
