<?php
/**
 * Plugin Name:       Meeting Minutes
 * Plugin URI:        https://equalizedigital.com
 * Description:       Manage and display meeting minutes as a custom post type with meta fields.
 * Version:           1.0.0
 * Author:            Equalize Digital
 * Author URI:        https://equalizedigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       meeting-minutes
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package EqualizeDigital\MeetingMinutes
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
				esc_html__( 'Meeting Minutes requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP or contact your host.', 'meeting-minutes' ),
				'7.4',
				PHP_VERSION
			)
			. '</p></div>';
		}
	);
	return;
}

// Plugin constants needed by the autoloader guard below.
define( 'EDMM_FILE', __FILE__ );
define( 'EDMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDMM_URL', plugin_dir_url( __FILE__ ) );

// Autoloads all EqualizeDigital\MeetingMinutes\* classes under includes/
// (PSR-4, see composer.json) - no per-class require_once line needed.
if ( ! file_exists( EDMM_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>'
			. esc_html__( 'Meeting Minutes is missing its autoloader. Reinstall from a released zip, or run `composer install` if developing from source.', 'meeting-minutes' )
			. '</p></div>';
		}
	);
	return;
}
require_once EDMM_DIR . 'vendor/autoload.php';

// EDMM_VERSION is defined only once the plugin can actually boot - the
// Pro plugin's own bootstrap checks defined( 'EDMM_VERSION' ) as its
// "is free active" signal, so defining it any earlier (e.g. alongside
// the other constants above) would make Pro think free is active even
// when the autoloader guard above just bailed.
define( 'EDMM_VERSION', '1.0.0' );

// Boot the plugin.
add_action(
	'plugins_loaded',
	function () {
		EqualizeDigital\MeetingMinutes\Plugin::get_instance()->boot();
	}
);
