<?php
/**
 * Settings page markup: shortcode builder + advanced settings.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
							data-default="20"
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
							data-default="Y/m/d"
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
							data-default="Y/m"
							class="regular-text"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Column Labels', 'edmm' ); ?></th>
					<td>
						<?php
						/**
						 * Filters the column-label builder fields (name => default label).
						 * Each entry renders a text input named $key with $value as both
						 * its placeholder and its display default. Pro plugin uses this
						 * to add its own column labels (location, livestream, etc.) to
						 * this same section instead of duplicating it.
						 *
						 * @since x.x.x
						 *
						 * @param array $label_fields Map of field name to default label.
						 */
						$label_fields = apply_filters(
							'edmm_shortcode_builder_label_fields',
							[
								'title_label'   => __( 'Title', 'edmm' ),
								'date_label'    => __( 'Date', 'edmm' ),
								'agenda_label'  => __( 'Agenda', 'edmm' ),
								'minutes_label' => __( 'Minutes', 'edmm' ),
							]
						);
						// A misbehaving filter callback returning a non-array
						// would otherwise throw a PHP warning and drop this
						// whole section instead of failing gracefully.
						$label_fields = is_array( $label_fields ) ? $label_fields : [];
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
						/**
						 * Filters the hide-column builder fields (name => label).
						 * Each entry renders a checkbox named $key that adds
						 * $key="true" to the shortcode when checked. Pro plugin
						 * uses this to add its own hide toggles (location,
						 * livestream, etc.) to this same section instead of
						 * duplicating it.
						 *
						 * @since x.x.x
						 *
						 * @param array $columns Map of field name to checkbox label.
						 */
						$columns = apply_filters(
							'edmm_shortcode_builder_hide_fields',
							[
								'hide_title'   => __( 'Title', 'edmm' ),
								'hide_date'    => __( 'Date', 'edmm' ),
								'hide_agenda'  => __( 'Agenda', 'edmm' ),
								'hide_minutes' => __( 'Minutes', 'edmm' ),
							]
						);
						// See the same guard on $label_fields above.
						$columns = is_array( $columns ) ? $columns : [];
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
				 *
				 * Added fields are automatically picked up by the generic builder
				 * script in SettingsPage::get_builder_script() - no matching JS
				 * needed on the Pro side. A checked checkbox becomes
				 * name="value-or-true"; any other field becomes name="value"
				 * whenever its value is non-empty and differs from its
				 * data-default attribute (defaults to "" when omitted).
				 *
				 * @since x.x.x
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

	<?php
	/**
	 * Fires inside the settings page after the main form. Pro plugin uses this
	 * for sections with their own form (e.g. license management) that don't use
	 * the Settings API.
	 *
	 * @since x.x.x
	 */
	do_action( 'edmm_settings_fields' );
	?>
</div>
