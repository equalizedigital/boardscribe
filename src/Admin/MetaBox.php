<?php
/**
 * Native meta box for Meeting Minutes fields.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\Admin;

/**
 * Registers post meta and provides a native admin meta box UI
 * for the four Meeting Minutes meta fields.
 */
class MetaBox {

	/**
	 * Hooks meta registration and meta box into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post_edmm_meeting_minutes', [ $this, 'save_meta' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueues the WordPress media library and the meta box picker script
	 * on the Meeting Minutes add/edit screen.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edmm_meeting_minutes' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_register_script( 'edmm-meta-box', false, [ 'media-upload' ], EDMM_VERSION, true );
		wp_enqueue_script( 'edmm-meta-box' );
		wp_add_inline_script( 'edmm-meta-box', $this->get_media_picker_script() );
	}

	/**
	 * Returns the inline JavaScript for the media library URL picker.
	 *
	 * @return string
	 */
	private function get_media_picker_script(): string {
		return <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	document.querySelectorAll( '.edmm-media-button' ).forEach( function ( button ) {
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
	 * Registers all four meeting meta fields so they are available
	 * in the REST API and the block editor.
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$common = [
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
		];

		register_post_meta( 'edmm_meeting_minutes', 'edmm_meeting_date', array_merge( $common, [
			'type'              => 'string',
			'description'       => __( 'Meeting date in Y-m-d format.', 'edmm' ),
			'sanitize_callback' => 'sanitize_text_field',
		] ) );

		register_post_meta( 'edmm_meeting_minutes', 'edmm_meeting_agenda_url', array_merge( $common, [
			'type'              => 'string',
			'description'       => __( 'URL to the meeting agenda document.', 'edmm' ),
			'sanitize_callback' => 'esc_url_raw',
		] ) );

		register_post_meta( 'edmm_meeting_minutes', 'edmm_meeting_minutes_url', array_merge( $common, [
			'type'              => 'string',
			'description'       => __( 'URL to the meeting minutes document.', 'edmm' ),
			'sanitize_callback' => 'esc_url_raw',
		] ) );

		register_post_meta( 'edmm_meeting_minutes', 'edmm_meeting_not_held', array_merge( $common, [
			'type'              => 'string',
			'description'       => __( 'Whether the meeting was not held.', 'edmm' ),
			'sanitize_callback' => 'sanitize_text_field',
		] ) );
	}

	/**
	 * Registers the Meeting Details meta box on the edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		/**
		 * Filters whether to show the native meta box UI.
		 * Pro plugin (or other integrations) can return false to replace this with their own UI.
		 *
		 * @param bool $show Whether to show the native meta box.
		 */
		if ( ! apply_filters( 'edmm_use_native_meta_boxes', true ) ) {
			return;
		}

		add_meta_box(
			'edmm_meeting_details',
			__( 'Meeting Details', 'edmm' ),
			[ $this, 'render_meta_box' ],
			'edmm_meeting_minutes',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the Meeting Details meta box HTML.
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$meeting_date       = get_post_meta( $post->ID, 'edmm_meeting_date', true );
		$meeting_agenda_url = get_post_meta( $post->ID, 'edmm_meeting_agenda_url', true );
		$meeting_minutes_url  = get_post_meta( $post->ID, 'edmm_meeting_minutes_url', true );
		$meeting_not_held   = get_post_meta( $post->ID, 'edmm_meeting_not_held', true );

		wp_nonce_field( 'edmm_save_meeting_meta', 'edmm_meeting_meta_nonce' );

		/**
		 * Fires before the default meta box fields are rendered.
		 * Pro plugin can use this to prepend additional fields.
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edmm_before_meta_box_fields', $post );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="edmm_meeting_date"><?php esc_html_e( 'Meeting Date', 'edmm' ); ?> <span aria-hidden="true">*</span></label>
					</th>
					<td>
						<input
							type="date"
							id="edmm_meeting_date"
							name="edmm_meeting_date"
							value="<?php echo esc_attr( $meeting_date ); ?>"
							required
							class="regular-text"
						/>
						<p class="description"><?php esc_html_e( 'Required. Format: YYYY-MM-DD', 'edmm' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="edmm_meeting_agenda_url"><?php esc_html_e( 'Agenda URL', 'edmm' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="edmm_meeting_agenda_url"
							name="edmm_meeting_agenda_url"
							value="<?php echo esc_attr( $meeting_agenda_url ); ?>"
							class="large-text"
							placeholder="https://"
						/>
						<button
							type="button"
							class="button edmm-media-button"
							data-target="edmm_meeting_agenda_url"
							data-title="<?php esc_attr_e( 'Choose Agenda File', 'edmm' ); ?>"
							data-insert="<?php esc_attr_e( 'Use this file', 'edmm' ); ?>"
						><?php esc_html_e( 'Media Library', 'edmm' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="edmm_meeting_minutes_url"><?php esc_html_e( 'Minutes URL', 'edmm' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="edmm_meeting_minutes_url"
							name="edmm_meeting_minutes_url"
							value="<?php echo esc_attr( $meeting_minutes_url ); ?>"
							class="large-text"
							placeholder="https://"
						/>
						<button
							type="button"
							class="button edmm-media-button"
							data-target="edmm_meeting_minutes_url"
							data-title="<?php esc_attr_e( 'Choose Minutes File', 'edmm' ); ?>"
							data-insert="<?php esc_attr_e( 'Use this file', 'edmm' ); ?>"
						><?php esc_html_e( 'Media Library', 'edmm' ); ?></button>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Meeting Not Held', 'edmm' ); ?>
					</th>
					<td>
						<label for="edmm_meeting_not_held">
							<input
								type="checkbox"
								id="edmm_meeting_not_held"
								name="edmm_meeting_not_held"
								value="1"
								<?php checked( '1', $meeting_not_held ); ?>
							/>
							<?php esc_html_e( 'This meeting was not held', 'edmm' ); ?>
						</label>
					</td>
				</tr>
				<?php
				/**
				 * Fires after the default meta box fields are rendered.
				 * Pro plugin can use this to append additional fields.
				 *
				 * @param \WP_Post $post The current post.
				 */
				do_action( 'edmm_meta_fields', $post );
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Saves the meeting meta fields on post save.
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['edmm_meeting_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edmm_meeting_meta_nonce'] ) ), 'edmm_save_meeting_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Meeting date — validate it is a real date in Y-m-d format.
		if ( isset( $_POST['edmm_meeting_date'] ) ) {
			$raw_date = sanitize_text_field( wp_unslash( $_POST['edmm_meeting_date'] ) );
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $raw_date );
			if ( $date_obj && $date_obj->format( 'Y-m-d' ) === $raw_date ) {
				update_post_meta( $post_id, 'edmm_meeting_date', $raw_date );
			}
		}

		// Agenda URL.
		if ( isset( $_POST['edmm_meeting_agenda_url'] ) ) {
			update_post_meta( $post_id, 'edmm_meeting_agenda_url', esc_url_raw( wp_unslash( $_POST['edmm_meeting_agenda_url'] ) ) );
		}

		// Minutes URL.
		if ( isset( $_POST['edmm_meeting_minutes_url'] ) ) {
			update_post_meta( $post_id, 'edmm_meeting_minutes_url', esc_url_raw( wp_unslash( $_POST['edmm_meeting_minutes_url'] ) ) );
		}

		// Not held — checkbox is absent when unchecked.
		$not_held = isset( $_POST['edmm_meeting_not_held'] ) ? '1' : '';
		update_post_meta( $post_id, 'edmm_meeting_not_held', $not_held );

		/**
		 * Fires after the default meta fields are saved.
		 * Pro plugin can hook here to save additional meta.
		 *
		 * @param int $post_id The post ID.
		 */
		do_action( 'edmm_save_meeting_meta', $post_id );
	}
}
