<?php
/**
 * Tests for MeetingMinutesEndpoint::validate_date_format().
 *
 * @package EqualizeDigital\MeetingMinutes
 */

use EqualizeDigital\MeetingMinutes\REST\MeetingMinutesEndpoint;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the REST validate_callback that gates held_date_format/
 * not_held_date_format, added to close a potential XSS vector where an
 * unauthenticated caller could otherwise control a PHP DateTime format
 * string reflected back in the public REST response.
 */
class MeetingMinutesEndpointValidateDateFormatTest extends TestCase {

	/**
	 * Ordinary date format strings are accepted.
	 *
	 * @dataProvider provide_valid_formats
	 * @param string $format Format string under test.
	 */
	public function test_accepts_valid_formats( string $format ): void {
		$this->assertTrue( MeetingMinutesEndpoint::validate_date_format( $format ) );
	}

	/**
	 * Data provider for valid date formats.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_valid_formats(): array {
		return [
			'default held format'     => [ 'Y/m/d' ],
			'default not-held format' => [ 'Y/m' ],
			'with time and dashes'    => [ 'Y-m-d H:i:s' ],
			'empty string'            => [ '' ],
		];
	}

	/**
	 * Rejects the backslash-escape trick that would otherwise force
	 * DateTime::format() to emit arbitrary literal characters, including
	 * ones that aren't allowed on their own (like angle brackets).
	 */
	public function test_rejects_backslash_escaped_payload(): void {
		$this->assertFalse( MeetingMinutesEndpoint::validate_date_format( '\\<\\s\\c\\r\\i\\p\\t\\>' ) );
	}

	/**
	 * Rejects a literal disallowed character even without any escaping.
	 */
	public function test_rejects_disallowed_characters(): void {
		$this->assertFalse( MeetingMinutesEndpoint::validate_date_format( '<script>' ) );
	}

	/**
	 * Non-string input (e.g. an array from a repeated query param) must
	 * fail validation cleanly rather than throw a TypeError.
	 */
	public function test_rejects_non_string_input_without_throwing(): void {
		$this->assertFalse( MeetingMinutesEndpoint::validate_date_format( [ 'not', 'a', 'string' ] ) );
	}

	/**
	 * Overly long input is rejected even if every character is otherwise
	 * allowed, to avoid feeding pathologically long strings into
	 * DateTime::format() once per result row.
	 */
	public function test_rejects_overly_long_input(): void {
		$this->assertFalse( MeetingMinutesEndpoint::validate_date_format( str_repeat( 'Y', 33 ) ) );
	}

	/**
	 * Input at exactly the length limit is still accepted.
	 */
	public function test_accepts_input_at_length_limit(): void {
		$this->assertTrue( MeetingMinutesEndpoint::validate_date_format( str_repeat( 'Y', 32 ) ) );
	}
}
