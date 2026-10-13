<?php
/**
 * Meeting start/end time helpers.
 *
 * @package EqualizeDigital\BoardScribe\Helpers
 * @since   x.x.x
 */

namespace EqualizeDigital\BoardScribe\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A meeting's optional start and end time, stored as site-local 24-hour
 * HH:MM strings in two post meta keys. A missing key means "no time": an
 * empty or invalid value is never stored.
 *
 * @since x.x.x
 */
class MeetingTime {

	const META_START = 'edbs_meeting_start_time';
	const META_END   = 'edbs_meeting_end_time';

	const DEFAULT_FORMAT = 'g:i a';

	/**
	 * Normalizes a stored or submitted 24-hour time to HH:MM.
	 *
	 * Accepts HH:MM and HH:MM:SS (what a time input can submit); anything
	 * else, including a missing zero-padded hour, is not a time.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value Candidate time.
	 * @return string HH:MM, or '' when not a valid time.
	 */
	public static function normalize( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', trim( $value ), $matches ) ) {
			return '';
		}

		return $matches[1] . ':' . $matches[2];
	}

	/**
	 * Meta sanitize callback: anything that isn't a valid time is stored as
	 * an empty string rather than as garbage.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize( $value ): string {
		return self::normalize( is_string( $value ) ? sanitize_text_field( $value ) : '' );
	}

	/**
	 * Deletes a time meta row that was just written empty, so an unset time
	 * is always an absent row whichever path wrote it (meta box, REST,
	 * importer or code). Hooked to added_post_meta and updated_post_meta.
	 *
	 * @since x.x.x
	 *
	 * @param int    $meta_id    Meta row ID (unused).
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Value just stored.
	 * @return void
	 */
	public static function prune_empty( $meta_id, $post_id, $meta_key, $meta_value ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by the hook signature.
		if ( ( self::META_START === $meta_key || self::META_END === $meta_key ) && '' === $meta_value ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Reads a meeting's normalized start and end time.
	 *
	 * @since x.x.x
	 *
	 * @param int $post_id The meeting post ID.
	 * @return array{start: string, end: string} Each '' when unset or invalid.
	 */
	public static function get( int $post_id ): array {
		return [
			'start' => self::normalize( get_post_meta( $post_id, self::META_START, true ) ),
			'end'   => self::normalize( get_post_meta( $post_id, self::META_END, true ) ),
		];
	}

	/**
	 * Whether a value is usable as a time_format.
	 *
	 * A narrower allowlist than the date formats: only the time characters
	 * a, A, g, G, h, H, i and s, plus plain separators. Characters such as
	 * T or e would print the server's timezone, and a backslash can't pass
	 * the REST validator that also checks the date formats.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value Candidate format.
	 * @return bool
	 */
	public static function is_valid_format( $value ): bool {
		return is_string( $value )
			&& strlen( $value ) <= 32
			&& (bool) preg_match( '/^[aAgGhHis\/\-.: ,]+$/', $value )
			&& (bool) preg_match( '/[gGhH]/', $value );
	}

	/**
	 * Sanitizes a time_format value, falling back to a default for anything
	 * that isn't a valid format.
	 *
	 * @since x.x.x
	 *
	 * @param mixed  $value         Raw time_format value.
	 * @param string $default_value Format used when the value is invalid.
	 * @return string
	 */
	public static function sanitize_format( $value, string $default_value ): string {
		$value = sanitize_text_field( (string) $value );

		return self::is_valid_format( $value ) ? $value : $default_value;
	}

	/**
	 * Formats a normalized time with a PHP date() format, English-only like
	 * the meeting date.
	 *
	 * @since x.x.x
	 *
	 * @param string $time   HH:MM.
	 * @param string $format PHP date() format.
	 * @return string The formatted time, or '' when $time isn't valid.
	 */
	public static function format( string $time, string $format ): string {
		// The leading "!" resets the date parts, so the result doesn't
		// depend on today's date.
		$date = \DateTime::createFromFormat( '!H:i', self::normalize( $time ) );

		return $date ? $date->format( $format ) : '';
	}

	/**
	 * Builds the escaped time markup shown under a meeting's date.
	 *
	 * @since x.x.x
	 *
	 * @param string $start  HH:MM start time, or ''.
	 * @param string $end    HH:MM end time, or ''.
	 * @param string $format PHP date() format.
	 * @return string Markup, or '' when neither time is valid.
	 */
	public static function markup( string $start, string $end, string $format ): string {
		$start = self::normalize( $start );
		$end   = self::normalize( $end );

		$start_html = '' !== $start ? self::element( $start, $format ) : '';
		$end_html   = '' !== $end ? self::element( $end, $format ) : '';

		if ( '' !== $start_html && '' !== $end_html ) {
			/* translators: 1: start time, 2: end time, e.g. "5:00 pm to 7:00 pm". */
			return sprintf( __( '%1$s to %2$s', 'boardscribe' ), $start_html, $end_html );
		}

		if ( '' !== $end_html ) {
			/* translators: %s: end time, e.g. "Ends 7:00 pm". */
			return sprintf( __( 'Ends %s', 'boardscribe' ), $end_html );
		}

		return $start_html;
	}

	/**
	 * Builds one <time> element.
	 *
	 * @param string $time   HH:MM.
	 * @param string $format PHP date() format.
	 * @return string
	 */
	private static function element( string $time, string $format ): string {
		return '<time datetime="' . esc_attr( $time ) . '">' . esc_html( self::format( $time, $format ) ) . '</time>';
	}
}
