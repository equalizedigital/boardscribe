<?php
/**
 * Shortcode builder page markup.
 *
 * The page is a React app (src/js/builder/, enqueued by
 * SettingsPage::enqueue_builder_script()) rendering every field from
 * the shared registry — see FieldRegistry::js_schema().
 *
 * @package EqualizeDigital\BoardScribe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p><?php esc_html_e( 'Configure your shortcode options below, then copy the generated shortcode and paste it into any page or post.', 'boardscribe' ); ?></p>

	<div id="edbs-shortcode-builder-root">
		<noscript><?php esc_html_e( 'The shortcode builder requires JavaScript.', 'boardscribe' ); ?></noscript>
	</div>
</div>
