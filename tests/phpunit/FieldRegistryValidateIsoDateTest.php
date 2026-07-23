<?php
/**
 * Tests for FieldRegistry::validate_iso_date_or_empty() and sanitize_iso_date().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the REST validate_callback/sanitize_callback pair that gates
 * start_date/end_date (see boardscribe#48 — fiscal-year-friendly date
 * range filtering, since included_years can't express a non-calendar-year
 * range).
 */
class FieldRegistryValidateIsoDateTest extends TestCase {

	/**
	 * An empty string is valid — it means "no filter", not "invalid date".
	 */
	public function test_empty_string_is_valid(): void {
		$this->assertTrue( FieldRegistry::validate_iso_date_or_empty( '' ) );
	}

	/**
	 * Ordinary Y-m-d dates are accepted.
	 *
	 * @dataProvider provide_valid_dates
	 * @param string $date Date string under test.
	 */
	public function test_accepts_valid_dates( string $date ): void {
		$this->assertTrue( FieldRegistry::validate_iso_date_or_empty( $date ) );
	}

	/**
	 * Data provider for valid Y-m-d dates.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_valid_dates(): array {
		return [
			'ordinary date' => [ '2024-03-15' ],
			'leap day'      => [ '2024-02-29' ],
			'year boundary' => [ '2023-12-31' ],
		];
	}

	/**
	 * A non-existent calendar date (e.g. Feb 30, or a non-leap-year Feb 29)
	 * is rejected outright via checkdate() rather than silently rolling
	 * forward to the next valid date - the same class of bug fixed in
	 * CsvImporter::parse_publish_date() (see boardscribe-pro).
	 *
	 * @dataProvider provide_invalid_calendar_dates
	 * @param string $date Date string under test.
	 */
	public function test_rejects_invalid_calendar_dates( string $date ): void {
		$this->assertFalse( FieldRegistry::validate_iso_date_or_empty( $date ) );
	}

	/**
	 * Data provider for well-formed but non-existent calendar dates.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_invalid_calendar_dates(): array {
		return [
			'february 30th'        => [ '2024-02-30' ],
			'non-leap-year feb 29' => [ '2023-02-29' ],
			'month 13'             => [ '2024-13-01' ],
			'day 32'               => [ '2024-01-32' ],
		];
	}

	/**
	 * Malformed strings (wrong separators, wrong segment lengths, extra
	 * text) are rejected.
	 *
	 * @dataProvider provide_malformed_strings
	 * @param string $date Date string under test.
	 */
	public function test_rejects_malformed_strings( string $date ): void {
		$this->assertFalse( FieldRegistry::validate_iso_date_or_empty( $date ) );
	}

	/**
	 * Data provider for malformed date strings.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_malformed_strings(): array {
		return [
			'slashes instead of dashes' => [ '2024/03/15' ],
			'two-digit year'            => [ '24-03-15' ],
			'trailing text'              => [ '2024-03-15 extra' ],
			'not a date at all'         => [ 'not-a-date' ],
		];
	}

	/**
	 * Non-string input (e.g. an array from a repeated query param) must
	 * fail validation cleanly rather than throw a TypeError.
	 */
	public function test_rejects_non_string_input_without_throwing(): void {
		$this->assertFalse( FieldRegistry::validate_iso_date_or_empty( [ 'not', 'a', 'string' ] ) );
	}

	/**
	 * sanitize_iso_date() passes a valid date through unchanged.
	 */
	public function test_sanitize_passes_through_a_valid_date(): void {
		$this->assertSame( '2024-03-15', FieldRegistry::sanitize_iso_date( '2024-03-15' ) );
	}

	/**
	 * sanitize_iso_date() falls back to an empty string (no filter) for an
	 * invalid calendar date, rather than passing through a value that
	 * would otherwise reach meta_query's BETWEEN/>=/<= comparisons unchecked.
	 */
	public function test_sanitize_falls_back_to_empty_string_for_invalid_date(): void {
		$this->assertSame( '', FieldRegistry::sanitize_iso_date( '2024-02-30' ) );
	}
}
