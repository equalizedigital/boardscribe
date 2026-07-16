<?php
/**
 * BoardScribe meeting custom post type registration.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\PostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and manages the edbs_boardscribe custom post type.
 */
class BoardScribeCPT {

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
	 * Registers the edbs_boardscribe custom post type.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'           => _x( 'BoardScribe Meetings', 'Post Type General Name', 'boardscribe' ),
			'singular_name'  => _x( 'BoardScribe Meeting', 'Post Type Singular Name', 'boardscribe' ),
			'menu_name'      => __( 'BoardScribe', 'boardscribe' ),
			'name_admin_bar' => __( 'BoardScribe Meeting', 'boardscribe' ),
		];

		$args = [
			'label'         => __( 'BoardScribe Meeting', 'boardscribe' ),
			'labels'        => $labels,
			'supports'      => [ 'title' ],
			// No public single/archive templates — meetings are only ever
			// displayed via the [edbs_boardscribe] shortcode's own REST endpoint.
			'public'        => false,
			'rewrite'       => false,
			'has_archive'   => false,
			'show_ui'       => true,
			// Intentionally still exposes the default /wp/v2/edbs_boardscribe
			// REST route (needed for the block editor meta box UI); this is fine
			// since published meetings are meant to be public records.
			'show_in_rest'  => true,
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-calendar',
		];

		/**
		 * Filters the edbs_boardscribe CPT registration args. Pro plugin uses
		 * this to enable public/rewrite/archive support or a custom capability_type.
		 *
		 * @since x.x.x
		 *
		 * @param array $args The register_post_type() args.
		 */
		$args = apply_filters( 'edbs_cpt_args', $args );

		/**
		 * Fires before the BoardScribe CPT is registered. Register taxonomies
		 * that need to bind to the CPT here.
		 *
		 * @since x.x.x
		 */
		do_action( 'edbs_before_register_cpt' );

		register_post_type( 'edbs_boardscribe', $args );

		/**
		 * Fires after the BoardScribe CPT is registered. Register taxonomies
		 * or rewrite rules here.
		 *
		 * @since x.x.x
		 */
		do_action( 'edbs_after_register_cpt' );
	}
}
