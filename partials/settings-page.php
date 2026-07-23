<?php
/**
 * Settings page markup: sidebar header + tabbed panel.
 *
 * @package EqualizeDigital\BoardScribe
 *
 * @var \EqualizeDigital\BoardScribe\Admin\SettingsPage $this        Settings page instance.
 * @var string                                          $current_tab Active tab slug.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap edbs-settings-wrap">
	<?php settings_errors( 'edbs_settings_group' ); ?>

	<div class="edbs-settings-header">
		<img
			src="<?php echo esc_url( EDBS_URL . 'assets/images/logo.svg' ); ?>"
			alt="<?php esc_attr_e( 'BoardScribe', 'boardscribe' ); ?>"
		/>
		<p class="edbs-settings-header-version">
			<?php
			/* translators: %s: plugin version number. */
			printf( esc_html__( 'Version %s', 'boardscribe' ), esc_html( EDBS_VERSION ) );
			?>
		</p>
		<?php $this->render_tabs( $current_tab ); ?>
	</div>

	<section class="edbs-settings-panel">
		<?php
		$this->render_panel_header( $current_tab );
		$this->render_tab_content( $current_tab );
		?>
	</section>
</div>
