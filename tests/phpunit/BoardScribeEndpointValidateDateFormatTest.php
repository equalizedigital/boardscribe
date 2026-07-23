<?php
/**
 * Tests for BoardScribeEndpoint::validate_date_format().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the REST validate_callback that gates held_date_format/
 * not_held_date_format, added to close a potential XSS vector where an
 * unauthenticated caller could otherwise control a PHP DateTime format
 * string reflected back in the public REST response.
 */
class BoardScribeEndpointValidateDateFormatTest extends TestCase {

	/**
	 * Ordinary date format strings are accepted.
	 *
	 * @dataProvider provide_valid_formats
	 * @param string $format Format string under test.
	 */
	public function test_accepts_valid_formats( string $format ): void {
		$this->assertTrue( BoardScribeEndpoint::validate_date_format( $format ) );
	}

	/**
	 * Data provider for valid date formats.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_valid_formats(): array {
		return [
			'default held format'     => [ 'F j, Y' ],
			'default not-held format' => [ 'F Y' ],
			'with time and dashes'    => [ 'Y-m-d H:i:s' ],
		];
	}

	/**
	 * An empty string is rejected rather than accepted, since a
	 * REST/shortcode caller only sees the field's own default applied
	 * when the param is omitted entirely - an explicit empty value must
	 * fail validation, or it would silently produce a blank date instead
	 * of falling back to the default. This also matters because
	 * sanitize_text_field() reduces a disallowed value like "<script>"
	 * down to "" before validate_date_format() ever sees it.
	 */
	public function test_rejects_empty_string(): void {
		$this->assertFalse( BoardScribeEndpoint::validate_date_format( '' ) );
	}

	/**
	 * Rejects the backslash-escape trick that would otherwise force
	 * DateTime::format() to emit arbitrary literal characters, including
	 * ones that aren't allowed on their own (like angle brackets).
	 */
	public function test_rejects_backslash_escaped_payload(): void {
		$this->assertFalse( BoardScribeEndpoint::validate_date_format( '\\<\\s\\c\\r\\i\\p\\t\\>' ) );
	}

	/**
	 * Rejects a literal disallowed character even without any escaping.
	 */
	public function test_rejects_disallowed_characters(): void {
		$this->assertFalse( BoardScribeEndpoint::validate_date_format( '<script>' ) );
	}

	/**
	 * Non-string input (e.g. an array from a repeated query param) must
	 * fail validation cleanly rather than throw a TypeError.
	 */
	public function test_rejects_non_string_input_without_throwing(): void {
		$this->assertFalse( BoardScribeEndpoint::validate_date_format( [ 'not', 'a', 'string' ] ) );
	}

	/**
	 * Overly long input is rejected even if every character is otherwise
	 * allowed, to avoid feeding pathologically long strings into
	 * DateTime::format() once per result row.
	 */
	public function test_rejects_overly_long_input(): void {
		$this->assertFalse( BoardScribeEndpoint::validate_date_format( str_repeat( 'Y', 33 ) ) );
	}

	/**
	 * Input at exactly the length limit is still accepted.
	 */
	public function test_accepts_input_at_length_limit(): void {
		$this->assertTrue( BoardScribeEndpoint::validate_date_format( str_repeat( 'Y', 32 ) ) );
	}
}
