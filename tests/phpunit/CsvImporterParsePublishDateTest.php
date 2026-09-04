<?php
/**
 * Tests for CsvImporter::parse_publish_date().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Import\CsvImporter;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the publish_date CSV column parser - in particular that a
 * calendar-invalid but numeric-looking date (e.g. "2024-02-30") is
 * rejected rather than silently rolled forward to the next valid date,
 * which is PHP's own DateTime constructor behavior and the opposite of
 * what "skip the row" is supposed to mean for the rest of this importer.
 */
class CsvImporterParsePublishDateTest extends TestCase {

	/**
	 * Calls the private parse_publish_date() method via reflection - no
	 * public entry point exists that isolates just this parsing step from
	 * the full CSV upload/file-handling flow.
	 *
	 * @param string $value The raw cell value.
	 * @return array|null
	 */
	private function parse( string $value ): ?array {
		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'parse_publish_date' );
		$method->setAccessible( true );

		return $method->invoke( $importer, $value );
	}

	/**
	 * A well-formed bare date parses successfully.
	 */
	public function test_valid_bare_date_parses(): void {
		$result = $this->parse( '2024-03-15' );

		$this->assertIsArray( $result );
		$this->assertSame( '2024-03-15 00:00:00', $result['local'] );
	}

	/**
	 * A calendar-invalid date (Feb 30 doesn't exist) is rejected, rather
	 * than PHP's DateTime silently rolling it forward to March 1st.
	 */
	public function test_invalid_calendar_date_is_rejected(): void {
		$this->assertNull( $this->parse( '2024-02-30' ) );
	}

	/**
	 * Mirrors test_invalid_calendar_date_is_rejected() for a different
	 * invalid day-of-month (April has 30 days, not 31).
	 */
	public function test_invalid_day_of_month_is_rejected(): void {
		$this->assertNull( $this->parse( '2024-04-31' ) );
	}

	/**
	 * Nonsense input is rejected.
	 */
	public function test_nonsense_input_is_rejected(): void {
		$this->assertNull( $this->parse( 'not a date at all' ) );
	}

	/**
	 * A date/time with an explicit UTC offset is parsed using that offset
	 * as authoritative - the gmt value reflects true UTC.
	 */
	public function test_explicit_offset_is_authoritative_for_gmt(): void {
		$result = $this->parse( '2024-03-15T14:30:00-05:00' );

		$this->assertIsArray( $result );
		$this->assertSame( '2024-03-15 19:30:00', $result['gmt'] );
	}
}
