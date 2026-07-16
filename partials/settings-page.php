<?php
/**
 * Settings page markup: advanced settings.
 *
 * @package EqualizeDigital\BoardScribe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

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
