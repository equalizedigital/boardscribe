<?php
/**
 * Plugin Name:       BoardScribe
 * Description:       Publish accessible, searchable meeting agendas and minutes on your site — built for public bodies, HOAs, and nonprofits that need open, organized records.
 * Version:           1.0.0
 * Author:            Equalize Digital
 * Author URI:        https://equalizedigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       boardscribe
 * Domain Path:       /languages
 * Requires at least: 6.7
 * Requires PHP:      7.4
 *
 * @package EqualizeDigital\BoardScribe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Minimum PHP version check.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>'
			. sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				esc_html__( 'BoardScribe requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP or contact your host.', 'boardscribe' ),
				'7.4',
				esc_html( PHP_VERSION )
			)
			. '</p></div>';
		}
	);
	return;
}

// Plugin constants needed by the autoloader guard below.
define( 'EDBS_FILE', __FILE__ );
define( 'EDBS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDBS_URL', plugin_dir_url( __FILE__ ) );

// Autoloads all EqualizeDigital\BoardScribe\* classes under includes/
// (PSR-4, see composer.json) - no per-class require_once line needed.
if ( ! file_exists( EDBS_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>'
			. esc_html__( 'BoardScribe is missing its autoloader. Reinstall from a released zip, or run `composer install` if developing from source.', 'boardscribe' )
			. '</p></div>';
		}
	);
	return;
}
require_once EDBS_DIR . 'vendor/autoload.php';

// EDBS_VERSION is defined only once the plugin can actually boot - the
// Pro plugin's own bootstrap checks defined( 'EDBS_VERSION' ) as its
// "is free active" signal, so defining it any earlier (e.g. alongside
// the other constants above) would make Pro think free is active even
// when the autoloader guard above just bailed.
define( 'EDBS_VERSION', '1.0.0' );

// Boot the plugin.
add_action(
	'plugins_loaded',
	function () {
		EqualizeDigital\BoardScribe\Plugin::get_instance()->boot();
	}
);
