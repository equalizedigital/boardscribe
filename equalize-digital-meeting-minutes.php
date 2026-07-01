<?php
/**
 * Plugin Name:       Equalize Digital Meeting Minutes
 * Plugin URI:        https://equalizedigital.com
 * Description:       Manage and display meeting minutes as a custom post type with meta fields.
 * Version:           1.0.0
 * Author:            Equalize Digital
 * Author URI:        https://equalizedigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       edmm
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.7
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
				esc_html__( 'Equalize Digital Meeting Minutes requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP or contact your host.', 'edmm' ),
				'7.4',
				PHP_VERSION
			)
			. '</p></div>';
		}
	);
	return;
}

// Plugin constants.
define( 'EDMM_VERSION', '1.0.0' );
define( 'EDMM_FILE', __FILE__ );
define( 'EDMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDMM_URL', plugin_dir_url( __FILE__ ) );

// Load all classes.
require_once EDMM_DIR . 'src/PostType/MeetingMinutes.php';
require_once EDMM_DIR . 'src/Admin/MetaBox.php';
require_once EDMM_DIR . 'src/Admin/SettingsPage.php';
require_once EDMM_DIR . 'src/REST/MeetingMinutesEndpoint.php';
require_once EDMM_DIR . 'src/Shortcode/MeetingMinutesShortcode.php';
require_once EDMM_DIR . 'src/Plugin.php';

// Boot the plugin.
add_action(
	'plugins_loaded',
	function () {
		EqualizeDigital\MeetingMinutes\Plugin::get_instance()->boot();
	}
);
