<?php
/**
 * Plugin settings page with shortcode builder.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\Admin;

use EqualizeDigital\MeetingMinutes\Plugin;

/**
 * Registers and renders the Meeting Minutes settings page.
 *
 * The page contains:
 * - A shortcode builder that generates a ready-to-copy shortcode
 *   based on the user's selections (no values are stored).
 * - An Advanced section for persistent settings (e.g. delete on uninstall).
 */
class SettingsPage {

	/**
	 * Hooks settings registration and admin menu into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_builder_script' ] );
	}

	/**
	 * Adds the Settings submenu under the Meeting Minutes CPT menu.
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=edmm_meeting_minutes',
			__( 'Meeting Minutes Settings', 'edmm' ),
			__( 'Settings', 'edmm' ),
			'manage_options',
			'edmm-settings',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Registers only the persistent plugin settings (not shortcode builder values).
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'edmm_settings_group',
			'edmm_settings',
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);

		add_settings_section(
			'edmm_advanced_section',
			__( 'Advanced', 'edmm' ),
			'__return_empty_string',
			'edmm-settings'
		);

		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete Data on Uninstall', 'edmm' ),
			[ $this, 'render_delete_on_uninstall' ],
			'edmm-settings',
			'edmm_advanced_section'
		);

		/**
		 * Fires after the default settings fields are registered.
		 * Pro plugin can use this to add additional settings fields.
		 *
		 * @param string $page The settings page slug.
		 */
		do_action( 'edmm_settings_fields', 'edmm-settings' );
	}

	/**
	 * Enqueues the inline shortcode builder script on the settings page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_builder_script( string $hook ): void {
		if ( 'edmm_meeting_minutes_page_edmm-settings' !== $hook ) {
			return;
		}

		// Inline script — no separate file needed for a small builder.
		wp_add_inline_script( 'jquery', $this->get_builder_script() );
	}

	/**
	 * Returns the inline JavaScript for the shortcode builder.
	 *
	 * @return string
	 */
	private function get_builder_script(): string {
		return <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	const form    = document.getElementById( 'edmm-shortcode-builder' );
	const output  = document.getElementById( 'edmm-shortcode-output' );
	const copyBtn = document.getElementById( 'edmm-copy-shortcode' );

	if ( ! form || ! output ) return;

	function buildShortcode() {
		const data   = new FormData( form );
		let shortcode = '[edmm_meeting_minutes';

		const years = data.get( 'included_years' ) || '';
		if ( years.trim() ) {
			shortcode += ' included_years="' + years.trim() + '"';
		}

		const postsPerPage = parseInt( data.get( 'posts_per_page' ), 10 );
		if ( postsPerPage && postsPerPage !== 20 ) {
			shortcode += ' posts_per_page="' + postsPerPage + '"';
		}

		const heldFormat = data.get( 'held_date_format' ) || '';
		if ( heldFormat && heldFormat !== 'Y/m/d' ) {
			shortcode += ' held_date_format="' + heldFormat + '"';
		}

		const notHeldFormat = data.get( 'not_held_date_format' ) || '';
		if ( notHeldFormat && notHeldFormat !== 'Y/m' ) {
			shortcode += ' not_held_date_format="' + notHeldFormat + '"';
		}

		[ 'title_label', 'date_label', 'agenda_label', 'minutes_label', 'agenda_link_label', 'minutes_link_label' ].forEach( function ( key ) {
			const val = ( data.get( key ) || '' ).trim();
			if ( val ) {
				shortcode += ' ' + key + '="' + val + '"';
			}
		} );

		[ 'hide_title', 'hide_date', 'hide_agenda', 'hide_minutes' ].forEach( function ( key ) {
			if ( data.get( key ) === 'true' ) {
				shortcode += ' ' + key + '="true"';
			}
		} );

		const cssClass = data.get( 'class' ) || '';
		if ( cssClass.trim() ) {
			shortcode += ' class="' + cssClass.trim() + '"';
		}

		shortcode += ']';
		output.value = shortcode;
	}

	form.addEventListener( 'input', buildShortcode );
	form.addEventListener( 'change', buildShortcode );

	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			output.select();
			document.execCommand( 'copy' );
			copyBtn.textContent = copyBtn.dataset.copied;
			setTimeout( function () {
				copyBtn.textContent = copyBtn.dataset.copy;
			}, 2000 );
		} );
	}

	buildShortcode();
} );
JS;
	}

	/**
	 * Renders the delete on uninstall settings field.
	 *
	 * @return void
	 */
	public function render_delete_on_uninstall(): void {
		$value = Plugin::get_setting( 'delete_on_uninstall' );
		?>
		<label for="edmm_delete_on_uninstall">
			<input
				type="checkbox"
				name="edmm_settings[delete_on_uninstall]"
				id="edmm_delete_on_uninstall"
				value="1"
				<?php checked( 1, (int) $value ); ?>
			/>
			<?php esc_html_e( 'Remove all meeting minutes posts and plugin settings when this plugin is deleted.', 'edmm' ); ?>
		</label>
		<p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: this cannot be undone.', 'edmm' ); ?></p>
		<?php
	}

	/**
	 * Sanitizes settings before saving.
	 *
	 * @param array $input Raw input from the settings form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		return [
			'delete_on_uninstall' => isset( $input['delete_on_uninstall'] ) ? 1 : 0,
		];
	}

	/**
	 * Renders the settings page HTML.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2><?php esc_html_e( 'Shortcode Builder', 'edmm' ); ?></h2>
			<p><?php esc_html_e( 'Configure your shortcode options below, then copy the generated shortcode and paste it into any page or post.', 'edmm' ); ?></p>

			<form id="edmm-shortcode-builder" onsubmit="return false;">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="edmm_builder_years"><?php esc_html_e( 'Filter by Years', 'edmm' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="edmm_builder_years"
									name="included_years"
									class="regular-text"
									placeholder="2023,2024"
								/>
								<p class="description"><?php esc_html_e( 'Comma-separated list of years. Leave blank to show all years.', 'edmm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="edmm_builder_posts_per_page"><?php esc_html_e( 'Records Per Page', 'edmm' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="edmm_builder_posts_per_page"
									name="posts_per_page"
									value="20"
									min="1"
									max="100"
									class="small-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="edmm_builder_held_date_format"><?php esc_html_e( 'Date Format (Held)', 'edmm' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="edmm_builder_held_date_format"
									name="held_date_format"
									value="Y/m/d"
									class="regular-text"
								/>
								<p class="description">
									<?php
									printf(
										/* translators: %s: link to PHP date format docs */
										esc_html__( 'PHP date format. %s', 'edmm' ),
										'<a href="https://www.php.net/manual/en/datetime.format.php" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Format reference', 'edmm' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="edmm_builder_not_held_date_format"><?php esc_html_e( 'Date Format (Not Held)', 'edmm' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="edmm_builder_not_held_date_format"
									name="not_held_date_format"
									value="Y/m"
									class="regular-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Column Labels', 'edmm' ); ?></th>
							<td>
								<?php
								$label_fields = [
									'title_label'  => __( 'Title', 'edmm' ),
									'date_label'   => __( 'Date', 'edmm' ),
									'agenda_label' => __( 'Agenda', 'edmm' ),
									'minutes_label'  => __( 'Minutes', 'edmm' ),
								];
								foreach ( $label_fields as $key => $default ) :
								?>
								<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
									<span style="width:60px;"><?php echo esc_html( $default ); ?></span>
									<input
										type="text"
										name="<?php echo esc_attr( $key ); ?>"
										class="regular-text"
										placeholder="<?php echo esc_attr( $default ); ?>"
									/>
								</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Leave blank to use the default label.', 'edmm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Link Labels', 'edmm' ); ?></th>
							<td>
								<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
									<span style="width:60px;"><?php esc_html_e( 'Agenda', 'edmm' ); ?></span>
									<input
										type="text"
										name="agenda_link_label"
										class="regular-text"
										placeholder="<?php esc_attr_e( 'View Agenda', 'edmm' ); ?>"
									/>
								</label>
								<label style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
									<span style="width:60px;"><?php esc_html_e( 'Minutes', 'edmm' ); ?></span>
									<input
										type="text"
										name="minutes_link_label"
										class="regular-text"
										placeholder="<?php esc_attr_e( 'View Minutes', 'edmm' ); ?>"
									/>
								</label>
								<p class="description"><?php esc_html_e( 'Text shown inside each link. Leave blank to use the default.', 'edmm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Hide Columns', 'edmm' ); ?></th>
							<td>
								<?php
								$columns = [
									'hide_title'  => __( 'Title', 'edmm' ),
									'hide_date'   => __( 'Date', 'edmm' ),
									'hide_agenda' => __( 'Agenda', 'edmm' ),
									'hide_minutes'  => __( 'Minutes', 'edmm' ),
								];
								foreach ( $columns as $key => $label ) :
								?>
								<label style="display:block; margin-bottom:4px;">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $key ); ?>"
										value="true"
									/>
									<?php echo esc_html( $label ); ?>
								</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="edmm_builder_class"><?php esc_html_e( 'Custom CSS Class', 'edmm' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="edmm_builder_class"
									name="class"
									class="regular-text"
								/>
							</td>
						</tr>
						<?php
						/**
						 * Fires inside the shortcode builder form after the default fields.
						 * Pro plugin can add additional builder fields here.
						 */
						do_action( 'edmm_shortcode_builder_fields' );
						?>
					</tbody>
				</table>
			</form>

			<h3><?php esc_html_e( 'Your Shortcode', 'edmm' ); ?></h3>
			<div style="display:flex; gap:8px; align-items:center; max-width:600px;">
				<input
					type="text"
					id="edmm-shortcode-output"
					readonly
					value="[edmm_meeting_minutes]"
					class="large-text"
					style="font-family:monospace;"
				/>
				<button
					type="button"
					id="edmm-copy-shortcode"
					class="button button-secondary"
					data-copy="<?php esc_attr_e( 'Copy', 'edmm' ); ?>"
					data-copied="<?php esc_attr_e( 'Copied!', 'edmm' ); ?>"
				>
					<?php esc_html_e( 'Copy', 'edmm' ); ?>
				</button>
			</div>

			<hr />

			<form method="post" action="options.php">
				<?php
				settings_fields( 'edmm_settings_group' );
				do_settings_sections( 'edmm-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
