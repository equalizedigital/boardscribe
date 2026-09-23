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

		// Every 'resource'-type field (built-in Agenda/Minutes, or a
		// plugin's own) automatically gets a `{key}_source`/`{key}_edit_url`/
		// `{key}_document_id` sibling meta - see MetaBoxFieldRegistry::all()'s own docblock,
		// which promises this happens "regardless of who added it". Loop
		// over the resolved registry instead of hardcoding just the two
		// core keys, so a plugin's resource field's siblings are also
		// present in the REST meta schema without that plugin needing to
		// register them itself.
		foreach ( MetaBoxFieldRegistry::all() as $field ) {
			if ( 'resource' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			register_post_meta(
				'edbs_meeting',
				$field['key'] . '_source',
				array_merge(
					$common,
					[
						'type'              => 'string',
						/* translators: %s: the resource field's meta key, e.g. edbs_agenda_url. */
						'description'       => sprintf( __( 'Which Add/Replace-modal source (media_library, external_url, or a plugin-registered id) produced %s\'s current value.', 'boardscribe' ), $field['key'] ),
						'sanitize_callback' => 'sanitize_key',
					]
				)
			);

			register_post_meta(
				'edbs_meeting',
				$field['key'] . '_edit_url',
				array_merge(
					$common,
					[
						'type'              => 'string',
						/* translators: %s: the resource field's meta key, e.g. edbs_agenda_url. */
						'description'       => sprintf( __( 'The wp-admin edit screen for %s\'s underlying post, when its source has one (e.g. a linked document) - empty for a plain Media Library file or external link.', 'boardscribe' ), $field['key'] ),
						'sanitize_callback' => 'esc_url_raw',
					]
				)
			);

			register_post_meta(
				'edbs_meeting',
				$field['key'] . '_document_id',
				array_merge(
					$common,
					[
						'type'              => 'integer',
						/* translators: %s: the resource field's meta key, e.g. edbs_agenda_url. */
						'description'       => sprintf( __( 'The linked post ID underlying %s\'s current value, when its source has one (e.g. a linked document) - 0 for a plain Media Library file or external link.', 'boardscribe' ), $field['key'] ),
						'sanitize_callback' => 'absint',
					]
				)
			);
		}
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

			// A 'resource' field's card chip is driven by which Add/Replace-
			// modal source produced its value (see resource-utils.js's
			// resolveResourceDisplay()), and its optional "Edit" action by
			// the underlying post's wp-admin edit URL, if its source has one
			// (e.g. a linked document) - both tracked in these sibling metas
			// rather than the field's own schema entry, since neither is a
			// field the registry renders its own row for.
			if ( 'resource' === ( $field['type'] ?? '' ) ) {
				$values[ $field['key'] . '_source' ]      = get_post_meta( $post->ID, $field['key'] . '_source', true );
				$values[ $field['key'] . '_edit_url' ]    = get_post_meta( $post->ID, $field['key'] . '_edit_url', true );
				$values[ $field['key'] . '_document_id' ] = get_post_meta( $post->ID, $field['key'] . '_document_id', true );
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

		// MetaBoxApp renders this hidden input itself, alongside every
		// other field's own input, from inside the same React app - if
		// the app failed to commit at all (a throwing custom control on
		// window.edbsMetaBoxControls/edbsResourceSources, a missing or
		// stale build, or a WP version lacking a component this bundle
		// needs), this input never reaches $_POST any more than the
		// fields it's meant to guard did. Bail before saving anything,
		// and before the edbs_save_meeting_meta action below (so a
		// plugin's own save handler for its own React-rendered fields -
		// e.g. Pro's Supporting Documents repeater - gets the same
		// protection), rather than letting a checkbox default to
		// "unchecked" or a repeater default to empty just because its
		// own $_POST key never existed (PRO-1394).
		if ( ! isset( $_POST['edbs_meta_box_rendered'] ) ) {
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
	 * @since 1.6.0
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

		// A 'resource_list' field's $_POST shape is a repeater array, not a
		// plain scalar - it's documented as always saved_externally (the
		// owning plugin persists it itself), and so is any 'attached'
		// field, regardless of its own $type. There's no cross-repo
		// compiler to enforce a plugin actually set that flag when
		// registering one, so this is a defensive backstop: without it, a
		// misconfigured field falls through to sanitize_text_field() on an
		// array below, which is fatal under this repo's own PHPUnit config
		// (convertWarningsToExceptions) and silently mangles the value in
		// production.
		if ( 'resource_list' === $type || ! empty( $field['attached'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: the misconfigured field's meta key. */
					esc_html__( 'The "%s" field is a resource_list or attached field and must set saved_externally => true.', 'boardscribe' ),
					esc_html( $key )
				),
				'1.6.0'
			);
			return;
		}

		if ( 'checkbox' === $type ) {
			update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? '1' : '' );
			return;
		}

		if ( ! isset( $_POST[ $key ] ) ) {
			return;
		}

		// Saved unconditionally, ahead of every branch below (including a
		// field's own sanitize_callback, which only handles the primary
		// value) - see save_resource_source()/save_resource_edit_url()'s
		// docblocks.
		if ( 'resource' === $type ) {
			$this->save_resource_source( $post_id, $key );
			$this->save_resource_edit_url( $post_id, $key );
			$this->save_resource_document_id( $post_id, $key );
		}

		// textarea needs sanitize_textarea_field() (preserves newlines)
		// rather than sanitize_text_field() (strips them, flattening a
		// multi-line value to one line) - both as this field's own
		// default sanitizer below, and as what a field's own
		// sanitize_callback receives, so a plugin's callback isn't handed
		// a value that's already lost its line breaks before it ever saw
		// them (PRO-1394). Every other type's behavior is unchanged.
		$pre_sanitized = 'textarea' === $type
			? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) )
			: sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

		if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			update_post_meta( $post_id, $key, call_user_func( $field['sanitize_callback'], $pre_sanitized ) );
			return;
		}

		if ( 'date' === $type ) {
			$raw_date = $pre_sanitized;
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $raw_date );
			if ( $date_obj && $date_obj->format( 'Y-m-d' ) === $raw_date ) {
				update_post_meta( $post_id, $key, $raw_date );
			}
			return;
		}

		if ( 'url' === $type || 'resource' === $type ) {
			// esc_url_raw() calls ltrim() internally, which is a TypeError
			// in PHP 8+ given an array (e.g. a URL field name submitted as
			// "{$key}[]") - only sanitize a genuine string, silently
			// dropping anything else rather than fataling the whole save.
			$raw_value = $_POST[ $key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitized (or discarded entirely) immediately below; only kept as its own variable so the is_string() guard can run before esc_url_raw() ever sees it.
			if ( is_string( $raw_value ) ) {
				update_post_meta( $post_id, $key, esc_url_raw( wp_unslash( $raw_value ) ) );
			}
			return;
		}

		update_post_meta( $post_id, $key, $pre_sanitized );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Saves a 'resource' field's `{key}_source` sibling meta - which
	 * Add/Replace-modal source (media_library, external_url, or a
	 * plugin-registered id on window.edbsResourceSources) produced the
	 * field's current value, stamped on by resource-modal.js and carried
	 * as a hidden `{key}_source` input alongside the field's own (see
	 * resource-field.js/resource-list-field.js/attached-repeater-field.js).
	 * Read here unconditionally rather than as another MetaBoxFieldRegistry
	 * entry, since it isn't a field the registry renders its own row for -
	 * every 'resource'-type field gets one automatically, free's own
	 * (Agenda/Minutes) and any a plugin adds alike. Sanitized with
	 * sanitize_key() rather than a hard enum so a plugin-registered source
	 * id round-trips without free needing to know it exists.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $post_id The post ID being saved.
	 * @param string $key     The 'resource' field's own meta key.
	 * @return void
	 */
	private function save_resource_source( int $post_id, string $key ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified once in save_meta() before this is called for every field.
		$source_key = $key . '_source';
		if ( ! isset( $_POST[ $source_key ] ) ) {
			return;
		}

		update_post_meta( $post_id, $source_key, sanitize_key( wp_unslash( $_POST[ $source_key ] ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Saves a 'resource' field's `{key}_edit_url` sibling meta - the
	 * wp-admin edit screen for the value's underlying post, when its
	 * source has one (e.g. Pro's linked-document source), carried as a
	 * hidden `{key}_edit_url` input alongside the field's own (see
	 * resource-field.js). Empty for a source with no underlying editable
	 * post (Media Library, external URL) - resource-field.js only renders
	 * an "Edit" action when this is non-empty. Same generic-mechanism
	 * shape as save_resource_source() - see that method's docblock.
	 *
	 * @since 1.1.0-alpha.1
	 *
	 * @param int    $post_id The post ID being saved.
	 * @param string $key     The 'resource' field's own meta key.
	 * @return void
	 */
	private function save_resource_edit_url( int $post_id, string $key ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified once in save_meta() before this is called for every field.
		$edit_url_key = $key . '_edit_url';
		if ( ! isset( $_POST[ $edit_url_key ] ) ) {
			return;
		}

		// esc_url_raw() calls ltrim() internally, which is a TypeError in
		// PHP 8+ given an array - only sanitize a genuine string.
		$raw_value = $_POST[ $edit_url_key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitized (or discarded entirely) immediately below; only kept as its own variable so the is_string() guard can run before esc_url_raw() ever sees it.
		if ( is_string( $raw_value ) ) {
			update_post_meta( $post_id, $edit_url_key, esc_url_raw( wp_unslash( $raw_value ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Saves a 'resource' field's `{key}_document_id` sibling meta - the
	 * underlying linked post's ID, when the value's source has one (e.g. a
	 * plugin's linked-document source), carried as a hidden
	 * `{key}_document_id` input alongside the field's own (see
	 * resource-field.js). 0 for a source with no underlying post (Media
	 * Library, external URL). Not read anywhere by this field's own
	 * rendering - persisted purely so a reverse lookup elsewhere (e.g. a
	 * "used by" panel on the linked post's own edit screen) has something
	 * reliable to read instead of only a plain URL. Same generic-mechanism
	 * shape as save_resource_source()/save_resource_edit_url() - see those
	 * methods' docblocks.
	 *
	 * @since 1.1.0-alpha.1
	 *
	 * @param int    $post_id The post ID being saved.
	 * @param string $key     The 'resource' field's own meta key.
	 * @return void
	 */
	private function save_resource_document_id( int $post_id, string $key ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified once in save_meta() before this is called for every field.
		$document_id_key = $key . '_document_id';
		if ( ! isset( $_POST[ $document_id_key ] ) ) {
			return;
		}

		update_post_meta( $post_id, $document_id_key, absint( wp_unslash( $_POST[ $document_id_key ] ) ) );
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
