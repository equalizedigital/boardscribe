<?php
/**
 * Settings page markup: shortcode builder + advanced settings.
 *
 * @package EqualizeDigital\BoardScribe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<h2><?php esc_html_e( 'Shortcode Builder', 'boardscribe' ); ?></h2>
	<p><?php esc_html_e( 'Configure your shortcode options below, then copy the generated shortcode and paste it into any page or post.', 'boardscribe' ); ?></p>

	<?php
	/**
	 * The merged field registry — free plugin's own fields plus anything
	 * Pro (or another plugin) added via edbs_shortcode_field_registry.
	 * Every field renders here, in the settings-page builder, from this
	 * single array; see FieldRegistry::add_core_fields() for the shape.
	 *
	 * @var array[] $edbs_builder_fields
	 */
	$edbs_builder_fields = \EqualizeDigital\BoardScribe\Shortcode\FieldRegistry::all();

	$edbs_builder_groups = [
		'general'       => [],
		'column_labels' => [],
		'link_labels'   => [],
		'hide_columns'  => [],
		'show_columns'  => [],
	];
	foreach ( $edbs_builder_fields as $edbs_builder_field ) {
		$edbs_group = $edbs_builder_field['group'] ?? 'general';
		if ( ! isset( $edbs_builder_groups[ $edbs_group ] ) ) {
			$edbs_group = 'general';
		}
		$edbs_builder_groups[ $edbs_group ][] = $edbs_builder_field;
	}

	$edbs_bundled_group_titles = [
		'column_labels' => __( 'Column Labels', 'boardscribe' ),
		'link_labels'   => __( 'Link Labels', 'boardscribe' ),
		'hide_columns'  => __( 'Hide Columns', 'boardscribe' ),
		'show_columns'  => __( 'Show Columns', 'boardscribe' ),
	];
	?>
	<form id="edbs-shortcode-builder" onsubmit="return false;">
		<table class="form-table" role="presentation">
			<tbody>
				<?php foreach ( $edbs_builder_groups['general'] as $edbs_field ) : ?>
				<tr>
					<th scope="row">
						<label for="edbs_builder_<?php echo esc_attr( $edbs_field['key'] ); ?>"><?php echo esc_html( $edbs_field['label'] ?? '' ); ?></label>
					</th>
					<td>
						<?php $this->render_field_control( $edbs_field ); ?>
					</td>
				</tr>
				<?php endforeach; ?>

				<?php if ( $edbs_builder_groups['column_labels'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edbs_bundled_group_titles['column_labels'] ); ?></th>
					<td>
						<?php foreach ( $edbs_builder_groups['column_labels'] as $edbs_field ) : ?>
							<?php $this->render_bundled_text_field( $edbs_field ); ?>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Leave blank to use the default label.', 'boardscribe' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $edbs_builder_groups['link_labels'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edbs_bundled_group_titles['link_labels'] ); ?></th>
					<td>
						<?php foreach ( $edbs_builder_groups['link_labels'] as $edbs_field ) : ?>
							<?php $this->render_bundled_text_field( $edbs_field ); ?>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Text shown inside each link. Leave blank to use the default.', 'boardscribe' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $edbs_builder_groups['hide_columns'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edbs_bundled_group_titles['hide_columns'] ); ?></th>
					<td>
						<?php foreach ( $edbs_builder_groups['hide_columns'] as $edbs_field ) : ?>
							<?php $this->render_bundled_checkbox_field( $edbs_field ); ?>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $edbs_builder_groups['show_columns'] ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $edbs_bundled_group_titles['show_columns'] ); ?></th>
					<td>
						<?php foreach ( $edbs_builder_groups['show_columns'] as $edbs_field ) : ?>
							<?php $this->render_bundled_checkbox_field( $edbs_field ); ?>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Enable additional columns.', 'boardscribe' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</form>

	<h3><?php esc_html_e( 'Your Shortcode', 'boardscribe' ); ?></h3>
	<div style="display:flex; gap:8px; align-items:center; max-width:600px;">
		<input
			type="text"
			id="edbs-shortcode-output"
			readonly
			value="[edbs_meeting_minutes]"
			class="large-text"
			style="font-family:monospace;"
		/>
		<button
			type="button"
			id="edbs-copy-shortcode"
			class="button button-secondary"
			data-copy="<?php esc_attr_e( 'Copy', 'boardscribe' ); ?>"
			data-copied="<?php esc_attr_e( 'Copied!', 'boardscribe' ); ?>"
		>
			<?php esc_html_e( 'Copy', 'boardscribe' ); ?>
		</button>
	</div>

	<hr />

	<form method="post" action="options.php">
		<?php
		settings_fields( 'edbs_settings_group' );
		do_settings_sections( 'edbs-settings' );
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
	do_action( 'edbs_settings_fields' );
	?>
</div>
