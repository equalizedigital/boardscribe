<?php
/**
 * Native meta box for BoardScribe meeting fields.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Admin;

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post meta and provides a native admin meta box UI, rendered by
 * a React app (src/js/metabox/) reading MetaBoxFieldRegistry::js_schema(),
 * for the BoardScribe meeting meta fields.
 */
class MetaBox {

	/**
	 * Hooks meta registration and meta box into WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post_edbs_meeting', [ $this, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueues the media library and the Meeting Details React app.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edbs_meeting' !== $screen->post_type ) {
			return;
		}

		if ( ! self::is_native_meta_box_enabled() ) {
			return;
		}

		// wp.media() backs the per-field "Media Library" button the React
		// app renders for fields with media_picker => true.
		wp_enqueue_media();

		// Built by `npm run build` (assets/build/ is gitignored); the
		// generated *.asset.php carries the bundle's wp-* dependencies
		// and a content-hash version.
		$asset_file = EDBS_DIR . 'assets/build/metabox/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [],
				'version'      => EDBS_VERSION,
			];

		wp_enqueue_script(
			'edbs-meta-box',
			EDBS_URL . 'assets/build/metabox/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'edbs-meta-box',
			EDBS_URL . 'assets/css/metabox.css',
			[ 'wp-components' ],
			EDBS_VERSION
		);
		wp_set_script_translations( 'edbs-meta-box', 'boardscribe' );
		wp_localize_script( 'edbs-meta-box', 'edbsMetaBoxFieldRegistry', MetaBoxFieldRegistry::js_schema() );
	}

	/**
	 * Registers the four meeting meta fields for the REST API and block editor.
	 *
	 * @since 1.0.0
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
			'edbs_meeting',
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
			'edbs_meeting',
			'edbs_agenda_url',
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
			'edbs_meeting',
			'edbs_minutes_url',
			array_merge(
				$common,
				[
					'type'              => 'string',
					'description'       => __( 'URL to the published minutes document for this meeting.', 'boardscribe' ),
					'sanitize_callback' => 'esc_url_raw',
				]
			)
		);

		register_post_meta(
			'edbs_meeting',
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
	 * Whether the native meta box UI (and its enqueued script/style) should
	 * be shown - checked once from both add_meta_box() and enqueue_scripts()
	 * so a Pro replacement UI suppresses both consistently.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private static function is_native_meta_box_enabled(): bool {
		/**
		 * Filters whether to show the native meta box UI. Return false to replace
		 * it with a custom UI.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $show Whether to show the native meta box.
		 */
		return (bool) apply_filters( 'edbs_use_native_meta_boxes', true );
	}

	/**
	 * Registers the Meeting Details meta box on the edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		if ( ! self::is_native_meta_box_enabled() ) {
			return;
		}

		add_meta_box(
			'edbs_meeting_details',
			__( 'Meeting Details', 'boardscribe' ),
			[ $this, 'render_meta_box' ],
			'edbs_meeting',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the Meeting Details meta box HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'edbs_save_meeting_meta', 'edbs_meeting_meta_nonce' );

		/**
		 * Fires before the Meeting Details React app's mount point. Pro plugin
		 * can use this to render PHP content above the fields (the field rows
		 * themselves are React-rendered from MetaBoxFieldRegistry — add a
		 * field there instead of trying to inject a row here).
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edbs_before_meta_box_fields', $post );

		$values = [];
		foreach ( MetaBoxFieldRegistry::all() as $field ) {
			if ( ! empty( $field['render_callback'] ) && is_callable( $field['render_callback'] ) ) {
				$values[ $field['key'] ] = call_user_func( $field['render_callback'], $post );
			} else {
				$values[ $field['key'] ] = get_post_meta( $post->ID, $field['key'], true );
			}
		}
		?>
		<div id="edbs-meeting-meta-box-root" data-values="<?php echo esc_attr( wp_json_encode( $values, JSON_UNESCAPED_SLASHES ) ); ?>">
			<noscript><?php esc_html_e( 'The Meeting Details fields require JavaScript.', 'boardscribe' ); ?></noscript>
		</div>
		<?php
	}

	/**
	 * Saves the meeting meta fields on post save.
	 *
	 * @since 1.0.0
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

		foreach ( MetaBoxFieldRegistry::all() as $field ) {
			$this->save_field( $post_id, $field );
		}

		/**
		 * Fires after the default meta fields are saved. Pro plugin can hook here
		 * to save additional meta.
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id The post ID.
		 */
		do_action( 'edbs_save_meeting_meta', $post_id );

		// Fall back to a generated title when the editor left it blank.
		$this->maybe_set_default_title( $post_id );
	}

	/**
	 * Sanitizes and saves one MetaBoxFieldRegistry field from $_POST. A
	 * field's own sanitize_callback takes priority; otherwise the field's
	 * type gets a sensible default sanitizer. An invalid date is left
	 * unsaved (old value kept) rather than overwritten with a blank one —
	 * matches the pre-React behavior. A field marked saved_externally is
	 * skipped entirely — the plugin that owns it saves it itself (see the
	 * edbs_meeting_meta_fields filter's docblock in MetaBoxFieldRegistry).
	 *
	 * @since 1.2.0
	 *
	 * @param int                  $post_id The post ID being saved.
	 * @param array<string, mixed> $field   Field descriptor from MetaBoxFieldRegistry.
	 * @return void
	 */
	private function save_field( int $post_id, array $field ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified once in save_meta() before this is called for every field.
		if ( ! empty( $field['saved_externally'] ) ) {
			return;
		}

		$key  = $field['key'];
		$type = $field['type'] ?? 'text';

		if ( 'checkbox' === $type ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '' );
			return;
		}

		if ( ! isset( $_POST[ $key ] ) ) {
			return;
		}

		if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			update_post_meta(
				$post_id,
				$key,
				call_user_func( $field['sanitize_callback'], sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) )
			);
			return;
		}

		if ( 'date' === $type ) {
			$raw_date = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $raw_date );
			if ( $date_obj && $date_obj->format( 'Y-m-d' ) === $raw_date ) {
				update_post_meta( $post_id, $key, $raw_date );
			}
			return;
		}

		if ( 'url' === $type || 'resource' === $type ) {
			update_post_meta( $post_id, $key, esc_url_raw( wp_unslash( $_POST[ $key ] ) ) );
			return;
		}

		update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Generates a default post title from the meeting date when the meeting was
	 * saved without one, so meetings always have a meaningful admin/list label.
	 *
	 * Runs after the meta is saved (so the current meeting date is available)
	 * and unhooks its own save handler around wp_update_post() to avoid a
	 * save_post recursion loop.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	private function maybe_set_default_title( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Only generate when the editor left the title blank.
		if ( '' !== trim( $post->post_title ) ) {
			return;
		}

		$meeting_date = get_post_meta( $post_id, 'edbs_meeting_date', true );
		$title        = $this->generate_default_title( (string) $meeting_date, $post_id );

		if ( '' === $title ) {
			return;
		}

		// Avoid re-entering save_meta() when wp_update_post() re-fires save_post.
		remove_action( 'save_post_edbs_meeting', [ $this, 'save_meta' ] );
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $title,
			]
		);
		add_action( 'save_post_edbs_meeting', [ $this, 'save_meta' ] );
	}

	/**
	 * Builds the default meeting title ("Board Meeting" plus the formatted
	 * meeting date). Public and filterable so Pro can reuse or override it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meeting_date The raw edbs_meeting_date meta value (Y-m-d).
	 * @param int    $post_id      The post ID the title is being generated for.
	 * @return string The generated title.
	 */
	public function generate_default_title( string $meeting_date, int $post_id = 0 ): string {
		$base        = _x( 'Board Meeting', 'default generated meeting title', 'boardscribe' );
		$date_object = BoardScribeEndpoint::parse_date( $meeting_date );

		if ( $date_object ) {
			$date_format = (string) get_option( 'date_format' );
			$formatted   = $date_object->format( '' !== $date_format ? $date_format : 'F j, Y' );
			$title       = sprintf(
				/* translators: 1: "Board Meeting" label, 2: formatted meeting date. */
				_x( '%1$s - %2$s', 'default generated meeting title with date', 'boardscribe' ),
				$base,
				$formatted
			);
		} else {
			$title = $base;
		}

		/**
		 * Filters the auto-generated title used when a meeting is saved without
		 * one. Pro plugin can override the format (e.g. include the meeting series).
		 *
		 * @since 1.0.0
		 *
		 * @param string $title        The generated title.
		 * @param string $meeting_date The raw meeting date meta value.
		 * @param int    $post_id      The post ID.
		 */
		return (string) apply_filters( 'edbs_default_meeting_title', $title, $meeting_date, $post_id );
	}
}
