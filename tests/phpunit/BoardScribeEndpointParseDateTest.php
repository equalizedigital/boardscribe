<?php
/**
 * Tests for BoardScribeEndpoint::parse_date().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers PRO-1309: DateTime::createFromFormat() is lenient about
 * out-of-range values (a month of 15 rolls over into the next year
 * instead of failing), so trying the four supported formats in order
 * could previously misdate a value stored in one format by accepting it
 * under an earlier, wrong format whose overflow happened to "succeed".
 */
class BoardScribeEndpointParseDateTest extends TestCase {

	/**
	 * Each supported format parses its own values correctly.
	 *
	 * @dataProvider provide_valid_dates
	 * @param string $input    Raw date string.
	 * @param string $expected Expected Y-m-d result.
	 */
	public function test_parses_valid_dates( string $input, string $expected ): void {
		$date = BoardScribeEndpoint::parse_date( $input );

		$this->assertNotNull( $date );
		$this->assertSame( $expected, $date->format( 'Y-m-d' ) );
	}

	/**
	 * Data provider for valid dates, one per supported format.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function provide_valid_dates(): array {
		return [
			'Y-m-d (ISO)'          => [ '2023-03-15', '2023-03-15' ],
			'Ymd (ACF default)'    => [ '20230315', '2023-03-15' ],
			'd/m/Y, day <= 12'     => [ '05/03/2023', '2023-03-05' ],
			'm/d/Y, day > 12'      => [ '03/15/2023', '2023-03-15' ],
		];
	}

	/**
	 * The exact regression from PRO-1309: a legacy m/d/Y value with a day
	 * greater than 12 must resolve to the date it actually represents
	 * (March 15, 2023), not silently roll over to a different date
	 * (2024-03-03) by being misparsed under d/m/Y first.
	 */
	public function test_does_not_misdate_overflow_ambiguous_value(): void {
		$date = BoardScribeEndpoint::parse_date( '03/15/2023' );

		$this->assertNotNull( $date );
		$this->assertSame( '2023-03-15', $date->format( 'Y-m-d' ) );
		$this->assertNotSame( '2024-03-03', $date->format( 'Y-m-d' ) );
	}

	/**
	 * A value that isn't a valid date under any supported format - not
	 * just out of range for one format, genuinely invalid everywhere
	 * (month 13, no format has a 13th month) - returns null instead of
	 * an overflow-mangled DateTime.
	 */
	public function test_rejects_a_date_invalid_in_every_format(): void {
		$this->assertNull( BoardScribeEndpoint::parse_date( '2023-13-45' ) );
	}

	/**
	 * A day/month combination invalid in both slash formats (e.g. 13 is
	 * not a valid month, and swapping it to a day of 13 with month 13
	 * doesn't help either) returns null rather than picking whichever
	 * format DateTime::createFromFormat() happens to overflow into a
	 * plausible-looking date.
	 */
	public function test_rejects_ambiguous_slash_date_invalid_both_ways(): void {
		$this->assertNull( BoardScribeEndpoint::parse_date( '13/13/2023' ) );
	}

	/**
	 * Feb 30 is invalid in Y-m-d regardless of leniency elsewhere.
	 */
	public function test_rejects_nonexistent_calendar_date(): void {
		$this->assertNull( BoardScribeEndpoint::parse_date( '2023-02-30' ) );
	}

	/**
	 * An empty string returns null without attempting to parse.
	 */
	public function test_empty_string_returns_null(): void {
		$this->assertNull( BoardScribeEndpoint::parse_date( '' ) );
	}

	/**
	 * Garbage input that matches no supported format's shape at all
	 * returns null rather than throwing.
	 */
	public function test_unrecognized_format_returns_null(): void {
		$this->assertNull( BoardScribeEndpoint::parse_date( 'not a date' ) );
	}

	/**
	 * A leap day is accepted in a leap year...
	 */
	public function test_accepts_leap_day_in_leap_year(): void {
		$date = BoardScribeEndpoint::parse_date( '2024-02-29' );

		$this->assertNotNull( $date );
		$this->assertSame( '2024-02-29', $date->format( 'Y-m-d' ) );
	}

	/**
	 * ...and rejected in a non-leap year, rather than rolling over to
	 * March 1st.
	 */
	public function test_rejects_leap_day_in_non_leap_year(): void {
		$this->assertNull( BoardScribeEndpoint::parse_date( '2023-02-29' ) );
	}
}
