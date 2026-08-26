<?php
/**
 * BoardScribe helpers.
 *
 * Provides utility functions for the BoardScribe plugin.
 *
 * @package EqualizeDigital\BoardScribe\Helpers
 * @since   1.0.0
 */

namespace EqualizeDigital\BoardScribe\Helpers;

use EqualizeDigital\BoardScribe\Plugin;

/**
 * Helper functions for BoardScribe.
 *
 * @package EqualizeDigital\BoardScribe\Helpers
 * @since   1.0.0
 */
class Helpers {

	/**
	 * Base URL that relative links are appended to.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://equalizedigital.com/boardscribe/';

	/**
	 * Builds an equalizedigital.com URL with UTM and environment parameters.
	 *
	 * Mirrors the link-tagging used by our other plugins so outbound clicks from
	 * the admin can be attributed to the plugin, the surface they came from, and
	 * whether the site is running free or Pro.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url        Base URL. A relative value (anything not starting
	 *                           with "http") is appended to the BoardScribe base URL.
	 * @param array  $query_vars Parameters to add or override. Supports the standard
	 *                           utm_source / utm_medium / utm_campaign / utm_content /
	 *                           utm_term keys plus any other key-value pairs.
	 *
	 * @return string The URL with the parameters added.
	 */
	public static function utm_link_builder( string $url = '', array $query_vars = [] ): string {
		// Relative URLs hang off the BoardScribe base URL.
		if ( 0 !== strpos( $url, 'http' ) ) {
			$url = self::BASE_URL . ltrim( $url, '/' );
		}

		$query_defaults = [
			'utm_source'       => 'boardscribe',
			'utm_medium'       => 'software',
			'utm_campaign'     => 'wordpress-general',
			'php_version'      => defined( 'PHP_VERSION' ) ? PHP_VERSION : '',
			'platform'         => 'wordpress',
			'platform_version' => get_bloginfo( 'version' ),
			'software'         => self::get_software_edition(),
			'software_version' => defined( 'EDBS_VERSION' ) ? EDBS_VERSION : '',
			'days_active'      => self::get_days_active(),
		];

		// Caller-supplied values win, but never blank out a default.
		$query_params = array_merge(
			$query_defaults,
			array_filter(
				$query_vars,
				function ( $value ) {
					return ! empty( $value );
				}
			)
		);

		/**
		 * Filters the query parameters appended to an outbound BoardScribe link.
		 *
		 * Pro uses this to report its own version alongside the free one.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $query_params The resolved parameters.
		 * @param string $url          The URL being built.
		 */
		$query_params = apply_filters( 'edbs_utm_query_args', $query_params, $url );

		// add_query_arg() encodes the values itself, so pass them raw.
		return add_query_arg( $query_params, $url );
	}

	/**
	 * Reports which edition of BoardScribe the site is running.
	 *
	 * Distinguishes an unlicensed Pro install from a licensed one: Pro can be
	 * active with an expired or never-entered key, which is exactly the segment
	 * worth telling apart in link attribution. Reads Pro's license status option
	 * directly rather than calling `LicenseManager::is_licensed()`, since the
	 * free plugin cannot depend on Pro's classes being loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return string One of "free", "pro-unlicensed", or "pro".
	 */
	private static function get_software_edition(): string {
		if ( ! defined( 'EDBS_PRO_VERSION' ) ) {
			return 'free';
		}

		return 'valid' === get_option( 'edbs_pro_license_status', '' ) ? 'pro' : 'pro-unlicensed';
	}

	/**
	 * Returns how many whole days the plugin has been active on this site.
	 *
	 * Falls back to 0 when the activation date is missing or unparseable (an
	 * install predating the activation hook, or one activated by dropping the
	 * directory in place rather than through the plugins screen).
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private static function get_days_active(): int {
		$activation_date = get_option( Plugin::ACTIVATION_DATE_OPTION, '' );

		if ( ! is_string( $activation_date ) || '' === $activation_date ) {
			return 0;
		}

		$activation_timestamp = strtotime( $activation_date . ' UTC' );

		if ( false === $activation_timestamp ) {
			return 0;
		}

		return max( 0, (int) floor( ( time() - $activation_timestamp ) / DAY_IN_SECONDS ) );
	}
}
