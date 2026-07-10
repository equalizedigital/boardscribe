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

	<?php
	/**
	 * The merged field registry — free plugin's own fields plus anything
	 * Pro (or another plugin) added via edmm_shortcode_field_registry.
	 * Every field renders here, in the settings-page builder, from this
	 * single array; see FieldRegistry::add_core_fields() for the shape.
	 *
	 * @var array[] $edmm_builder_fields
	 */
	$edmm_builder_fields = \EqualizeDigital\MeetingMinutes\Shortcode\FieldRegistry::all();

	$edmm_builder_groups = [
		'general'       => [],
		'column_labels' => [],
		'link_labels'   => [],
		'hide_columns'  => [],
		'show_columns'  => [],
	];
	foreach ( $edmm_builder_fields as $edmm_builder_field ) {
		$edmm_group = $edmm_builder_field['group'] ?? 'general';
		if ( ! isset( $edmm_builder_groups[ $edmm_group ] ) ) {
			$edmm_group = 'general';
		}
		$edmm_builder_groups[ $edmm_group ][] = $edmm_builder_field;
	}

	$edmm_bundled_group_titles = [
		'column_labels' => __( 'Column Labels', 'edmm' ),
		'link_labels'   => __( 'Link Labels', 'edmm' ),
		'hide_columns'  => __( 'Hide Columns', 'edmm' ),
		'show_columns'  => __( 'Show Columns', 'edmm' ),
	];
	?>
	<form id="edmm-shortcode-builder" onsubmit="return false;">
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $edmm_builder_groups['general'] as $edmm_field ) : ?>
				<tr>
					<th scope="row">
						<label for="edmm_builder_<?php echo esc_attr( $edmm_field['key'] ); ?>"><?php echo esc_html( $edmm_field['label'] ?? '' ); ?></label>
					</th>
					<td>
						<?php $this->render_field_control( $edmm_field ); ?>
					</td>
				</tr>
				<?php endforeach; ?>

				<?php if ( $edmm_builder_groups['column_labels'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edmm_bundled_group_titles['column_labels'] ); ?></th>
					<td>
						<?php foreach ( $edmm_builder_groups['column_labels'] as $edmm_field ) : ?>
							<?php $this->render_bundled_text_field( $edmm_field ); ?>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Leave blank to use the default label.', 'edmm' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $edmm_builder_groups['link_labels'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edmm_bundled_group_titles['link_labels'] ); ?></th>
					<td>
						<?php foreach ( $edmm_builder_groups['link_labels'] as $edmm_field ) : ?>
							<?php $this->render_bundled_text_field( $edmm_field ); ?>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Text shown inside each link. Leave blank to use the default.', 'edmm' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $edmm_builder_groups['hide_columns'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edmm_bundled_group_titles['hide_columns'] ); ?></th>
					<td>
						<?php foreach ( $edmm_builder_groups['hide_columns'] as $edmm_field ) : ?>
							<?php $this->render_bundled_checkbox_field( $edmm_field ); ?>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $edmm_builder_groups['show_columns'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edmm_bundled_group_titles['show_columns'] ); ?></th>
					<td>
						<?php foreach ( $edmm_builder_groups['show_columns'] as $edmm_field ) : ?>
							<?php $this->render_bundled_checkbox_field( $edmm_field ); ?>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Enable additional columns.', 'edmm' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
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
