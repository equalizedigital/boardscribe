<?php
/**
 * Native meta box for Meeting Minutes fields.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post meta and provides a native admin meta box UI
 * for the four Meeting Minutes meta fields.
 */
class MetaBox {

	/**
	 * Hooks meta registration and meta box into WordPress.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post_edbs_boardscribe', [ $this, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueues the media library and the meta box picker script.
	 *
	 * @since x.x.x
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edbs_boardscribe' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_register_script( 'edbs-meta-box', false, [ 'media-upload' ], EDBS_VERSION, true );
		wp_enqueue_script( 'edbs-meta-box' );
		wp_add_inline_script( 'edbs-meta-box', $this->get_media_picker_script() );
	}

	/**
	 * Returns the inline JavaScript for the media library URL picker.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	private function get_media_picker_script(): string {
		return <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.edbs-media-button' ).forEach( function ( button ) {
		button.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			var targetId = this.dataset.target;
			var title    = this.dataset.title;
			var insert   = this.dataset.insert;

			var frame = wp.media( {
				title:    title,
				button:   { text: insert },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var field = document.getElementById( targetId );
				if ( field ) {
					field.value = attachment.url;
				}
			} );

			frame.open();
		} );
	} );
} );
JS;
	}

	/**
	 * Registers the four meeting meta fields for the REST API and block editor.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$common = [
			'single'        => true,
			'show_in_rest'  => true,
			// $object_id is 0 when the REST API is authorizing meta for a post
			// that doesn't exist yet (e.g. new-post autosave), so fall back to
			// the general capability check in that case.
			'auth_callback' => fn( $allowed, $meta_key, $object_id ) => $object_id
				? current_user_can( 'edit_post', $object_id )
				: current_user_can( 'edit_posts' ),
		];

		register_post_meta(
			'edbs_boardscribe',
			'edbs_meeting_date',
			array_merge(
				$common,
				[
					'type'              => 'string',
					'description'       => __( 'Meeting date in Y-m-d format.', 'boardscribe' ),
					'sanitize_callback' => 'sanitize_text_field',
				]
			)
		);

		register_post_meta(
			'edbs_boardscribe',
			'edbs_meeting_agenda_url',
			array_merge(
				$common,
				[
					'type'              => 'string',
					'description'       => __( 'URL to the meeting agenda document.', 'boardscribe' ),
					'sanitize_callback' => 'esc_url_raw',
				]
			)
		);

		register_post_meta(
			'edbs_boardscribe',
			'edbs_meeting_minutes_url',
			array_merge(
				$common,
				[
					'type'              => 'string',
					'description'       => __( 'URL to the meeting minutes document.', 'boardscribe' ),
					'sanitize_callback' => 'esc_url_raw',
				]
			)
		);

		register_post_meta(
			'edbs_boardscribe',
			'edbs_meeting_not_held',
			array_merge(
				$common,
				[
					'type'              => 'string',
					'description'       => __( 'Whether the meeting was not held.', 'boardscribe' ),
					'sanitize_callback' => 'sanitize_text_field',
				]
			)
		);
	}

	/**
	 * Registers the Meeting Details meta box on the edit screen.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		/**
		 * Filters whether to show the native meta box UI. Return false to replace
		 * it with a custom UI.
		 *
		 * @since x.x.x
		 *
		 * @param bool $show Whether to show the native meta box.
		 */
		if ( ! apply_filters( 'edbs_use_native_meta_boxes', true ) ) {
			return;
		}

		add_meta_box(
			'edbs_meeting_details',
			__( 'Meeting Details', 'boardscribe' ),
			[ $this, 'render_meta_box' ],
			'edbs_boardscribe',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the Meeting Details meta box HTML.
	 *
	 * @since x.x.x
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$meeting_date        = get_post_meta( $post->ID, 'edbs_meeting_date', true );
		$meeting_agenda_url  = get_post_meta( $post->ID, 'edbs_meeting_agenda_url', true );
		$meeting_minutes_url = get_post_meta( $post->ID, 'edbs_meeting_minutes_url', true );
		$meeting_not_held    = get_post_meta( $post->ID, 'edbs_meeting_not_held', true );

		wp_nonce_field( 'edbs_save_meeting_meta', 'edbs_meeting_meta_nonce' );

		/**
		 * Fires before the default meta box fields are rendered. Pro plugin can
		 * use this to prepend additional fields.
		 *
		 * @since x.x.x
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edbs_before_meta_box_fields', $post );

		require EDBS_DIR . 'partials/meta-box.php';
	}

	/**
	 * Saves the meeting meta fields on post save.
	 *
	 * @since x.x.x
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['edbs_meeting_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edbs_meeting_meta_nonce'] ) ), 'edbs_save_meeting_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Meeting date — validate it is a real date in Y-m-d format.
		if ( isset( $_POST['edbs_meeting_date'] ) ) {
			$raw_date = sanitize_text_field( wp_unslash( $_POST['edbs_meeting_date'] ) );
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $raw_date );
			if ( $date_obj && $date_obj->format( 'Y-m-d' ) === $raw_date ) {
				update_post_meta( $post_id, 'edbs_meeting_date', $raw_date );
			}
		}

		// Agenda URL.
		if ( isset( $_POST['edbs_meeting_agenda_url'] ) ) {
			update_post_meta( $post_id, 'edbs_meeting_agenda_url', esc_url_raw( wp_unslash( $_POST['edbs_meeting_agenda_url'] ) ) );
		}

		// Minutes URL.
		if ( isset( $_POST['edbs_meeting_minutes_url'] ) ) {
			update_post_meta( $post_id, 'edbs_meeting_minutes_url', esc_url_raw( wp_unslash( $_POST['edbs_meeting_minutes_url'] ) ) );
		}

		// Not held — checkbox is absent when unchecked.
		$not_held = isset( $_POST['edbs_meeting_not_held'] ) ? '1' : '';
		update_post_meta( $post_id, 'edbs_meeting_not_held', $not_held );

		/**
		 * Fires after the default meta fields are saved. Pro plugin can hook here
		 * to save additional meta.
		 *
		 * @since x.x.x
		 *
		 * @param int $post_id The post ID.
		 */
		do_action( 'edbs_save_meeting_meta', $post_id );
	}
}
