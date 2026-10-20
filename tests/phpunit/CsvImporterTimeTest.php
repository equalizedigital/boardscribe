<?php
/**
 * Tests for importing optional meeting start/end times from CSV.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Helpers\MeetingTime;
use EqualizeDigital\BoardScribe\Import\CsvImporter;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Time columns are optional, never required, never discard a meeting, and
 * old files import exactly as they did before.
 */
class CsvImporterTimeTest extends TestCase {

	/**
	 * Path to the temp CSV file created for the running test, if any.
	 *
	 * @var string|null
	 */
	private ?string $csv_path = null;

	/**
	 * Removes the temp CSV file and query args after each test.
	 */
	public function tear_down(): void {
		if ( null !== $this->csv_path && file_exists( $this->csv_path ) ) {
			unlink( $this->csv_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		}
		$this->csv_path = null;
		unset( $_GET['edbs_import_success'], $_GET['edbs_import_invalid_times'], $_GET['edbs_import_duplicates'] );

		parent::tear_down();
	}

	/**
	 * Imports a CSV and returns process_csv()'s result.
	 *
	 * @param string[]                $headers Header row.
	 * @param array<int, string[]>    $rows    Data rows.
	 * @param string                  $prefix  Bytes written before the file (e.g. a BOM).
	 * @return array
	 */
	private function import( array $headers, array $rows, string $prefix = '' ): array {
		$this->csv_path = tempnam( sys_get_temp_dir(), 'edbs-csv-time-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam

		$handle = fopen( $this->csv_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $handle, $prefix ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $handle, $headers, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row, ',', '"', '' );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$method = new \ReflectionMethod( CsvImporter::class, 'process_csv' );
		$method->setAccessible( true );

		return $method->invoke( new CsvImporter(), $this->csv_path );
	}

	/**
	 * Returns every meeting with this title, oldest first.
	 *
	 * @param string $title Meeting title.
	 * @return \WP_Post[]
	 */
	private function meetings( string $title ): array {
		return get_posts(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'any',
				'title'       => $title,
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			]
		);
	}

	/**
	 * A file with no time columns imports exactly as before: no time meta at
	 * all, and nothing reported about times.
	 */
	public function test_old_format_file_imports_unchanged(): void {
		$result = $this->import(
			[ 'title', 'date', 'agenda_url' ],
			[ [ 'Regular Meeting', '2024-03-15', 'https://example.com/a.pdf' ] ]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['invalid_times'] );

		$meeting = $this->meetings( 'Regular Meeting' )[0];
		$this->assertFalse( metadata_exists( 'post', $meeting->ID, MeetingTime::META_START ) );
		$this->assertFalse( metadata_exists( 'post', $meeting->ID, MeetingTime::META_END ) );
		$this->assertSame( 'https://example.com/a.pdf', get_post_meta( $meeting->ID, 'edbs_agenda_url', true ) );
	}

	/**
	 * The time columns are recognised, documented and never required.
	 */
	public function test_time_columns_are_optional(): void {
		$columns = ( new CsvImporter() )->columns();

		$this->assertFalse( $columns['start_time']['required'] );
		$this->assertFalse( $columns['end_time']['required'] );
		$this->assertSame( [ 'title', 'date', 'start_time', 'end_time' ], array_slice( array_keys( $columns ), 0, 4 ) );
	}

	/**
	 * Provides cell values and the normalized time stored for them.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function stored_time_provider(): array {
		return [
			'24 hour'     => [ '17:00', '17:00' ],
			'12 hour'     => [ '5:00 PM', '17:00' ],
			'hour only'   => [ '5pm', '17:00' ],
			'dotted'      => [ '5:00p.m.', '17:00' ],
			'midnight'    => [ '12am', '00:00' ],
			'noon'        => [ '12pm', '12:00' ],
			'with spaces' => [ '  9:30 am ', '09:30' ],
		];
	}

	/**
	 * Start and end times are stored normalized.
	 *
	 * @dataProvider stored_time_provider
	 *
	 * @param string $cell     CSV cell.
	 * @param string $expected Stored value.
	 */
	public function test_times_are_stored_normalized( string $cell, string $expected ): void {
		$result = $this->import(
			[ 'title', 'date', 'start_time', 'end_time' ],
			[ [ 'Regular Meeting', '2024-03-15', $cell, $cell ] ]
		);

		$meeting = $this->meetings( 'Regular Meeting' )[0];

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['invalid_times'] );
		$this->assertSame( $expected, get_post_meta( $meeting->ID, MeetingTime::META_START, true ) );
		$this->assertSame( $expected, get_post_meta( $meeting->ID, MeetingTime::META_END, true ) );
	}

	/**
	 * An end time without a start time is kept.
	 */
	public function test_end_time_only(): void {
		$this->import(
			[ 'title', 'date', 'start_time', 'end_time' ],
			[ [ 'Regular Meeting', '2024-03-15', '', '7pm' ] ]
		);

		$meeting = $this->meetings( 'Regular Meeting' )[0];

		$this->assertFalse( metadata_exists( 'post', $meeting->ID, MeetingTime::META_START ) );
		$this->assertSame( '19:00', get_post_meta( $meeting->ID, MeetingTime::META_END, true ) );
	}

	/**
	 * A time that can't be read is dropped and counted, the other time on the
	 * row and the meeting itself are kept.
	 */
	public function test_unreadable_time_is_dropped_and_reported_but_the_meeting_imports(): void {
		$result = $this->import(
			[ 'title', 'date', 'start_time', 'end_time' ],
			[
				[ 'Bad Start', '2024-03-15', 'soon', '7pm' ],
				[ 'Bad Both', '2024-03-16', '17', '0.708' ],
				[ 'Good', '2024-03-17', '5pm', '' ],
			]
		);

		$this->assertSame( 3, $result['imported'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( 2, $result['invalid_times'], 'Counted once per row.' );

		$bad_start = $this->meetings( 'Bad Start' )[0];
		$this->assertFalse( metadata_exists( 'post', $bad_start->ID, MeetingTime::META_START ) );
		$this->assertSame( '19:00', get_post_meta( $bad_start->ID, MeetingTime::META_END, true ) );

		$bad_both = $this->meetings( 'Bad Both' )[0];
		$this->assertFalse( metadata_exists( 'post', $bad_both->ID, MeetingTime::META_START ) );
		$this->assertFalse( metadata_exists( 'post', $bad_both->ID, MeetingTime::META_END ) );

		$this->assertSame( '17:00', get_post_meta( $this->meetings( 'Good' )[0]->ID, MeetingTime::META_START, true ) );
	}

	/**
	 * edbs_csv_import_row_meta callbacks see the normalized time, or '' for an
	 * unreadable one, never the raw cell.
	 */
	public function test_row_meta_action_receives_normalized_times(): void {
		$seen     = [];
		$callback = static function ( int $post_id, array $data ) use ( &$seen ): void {
			$seen[] = [ $data['start_time'] ?? null, $data['end_time'] ?? null ];
		};
		add_action( 'edbs_csv_import_row_meta', $callback, 10, 2 );

		$this->import(
			[ 'title', 'date', 'start_time', 'end_time' ],
			[
				[ 'One', '2024-03-15', '5 PM', 'nonsense' ],
				[ 'Two', '2024-03-16', '', '' ],
			]
		);

		remove_action( 'edbs_csv_import_row_meta', $callback, 10 );

		$this->assertSame( [ [ '17:00', '' ], [ '', '' ] ], $seen );
	}

	/**
	 * Two meetings with the same title on the same day import as two when
	 * their start times differ, and re-importing the same file adds nothing.
	 */
	public function test_same_title_same_day_different_times_are_both_imported(): void {
		$headers = [ 'title', 'date', 'start_time' ];
		$rows    = [
			[ 'Budget Session', '2024-03-15', '9:00 AM' ],
			[ 'Budget Session', '2024-03-15', '6:00 PM' ],
		];

		$first = $this->import( $headers, $rows );

		$this->assertSame( 2, $first['imported'] );
		$this->assertSame( 0, $first['duplicates'] );
		$this->assertCount( 2, $this->meetings( 'Budget Session' ) );

		$second = $this->import( $headers, $rows );

		$this->assertSame( 0, $second['imported'] );
		$this->assertSame( 2, $second['duplicates'] );
		$this->assertCount( 2, $this->meetings( 'Budget Session' ) );
	}

	/**
	 * A row whose time can't be read still counts it when the row is then
	 * skipped as a duplicate, so the notice accounts for every unreadable time.
	 */
	public function test_unreadable_time_is_counted_when_the_row_is_a_duplicate(): void {
		$result = $this->import(
			[ 'title', 'date', 'start_time' ],
			[
				[ 'Board', '2024-03-12', '09:00' ],
				[ 'Board', '2024-03-12', '5.30pm' ],
				[ 'Board', '2024-03-12', '18' ],
			]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 2, $result['duplicates'] );
		$this->assertSame( 2, $result['invalid_times'] );
	}

	/**
	 * A time cell holding only a non-breaking space is an empty cell, not an
	 * unreadable time.
	 */
	public function test_non_breaking_space_time_cell_is_empty_not_unreadable(): void {
		$result = $this->import(
			[ 'title', 'date', 'start_time' ],
			[ [ 'Board', '2024-03-12', "\xC2\xA0" ] ]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['invalid_times'] );
	}

	/**
	 * Without times the duplicate rule is exactly what it was: same title and
	 * date is a duplicate, within a file and across imports.
	 */
	public function test_untimed_duplicates_are_still_duplicates(): void {
		$headers = [ 'title', 'date', 'start_time' ];

		$result = $this->import(
			$headers,
			[
				[ 'Regular Meeting', '2024-03-15', '' ],
				[ 'Regular Meeting', '2024-03-15', '' ],
			]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['duplicates'] );
	}

	/**
	 * Re-importing a file with times over meetings that exist without any does
	 * not add the times: the importer only ever creates meetings.
	 */
	public function test_reimport_does_not_backfill_times_onto_existing_meetings(): void {
		$this->import( [ 'title', 'date' ], [ [ 'Regular Meeting', '2024-03-15' ] ] );

		$result = $this->import(
			[ 'title', 'date', 'start_time' ],
			[ [ 'Regular Meeting', '2024-03-15', '5pm' ] ]
		);

		$meetings = $this->meetings( 'Regular Meeting' );

		$this->assertSame( 0, $result['imported'] );
		$this->assertSame( 1, $result['duplicates'] );
		$this->assertCount( 1, $meetings );
		$this->assertFalse( metadata_exists( 'post', $meetings[0]->ID, MeetingTime::META_START ) );
	}

	/**
	 * A row with no time still matches an existing meeting that has one, so an
	 * old file re-imported after times were added doesn't duplicate anything.
	 */
	public function test_untimed_row_matches_an_existing_timed_meeting(): void {
		$this->import( [ 'title', 'date', 'start_time' ], [ [ 'Regular Meeting', '2024-03-15', '5pm' ] ] );

		$result = $this->import( [ 'title', 'date' ], [ [ 'Regular Meeting', '2024-03-15' ] ] );

		$this->assertSame( 0, $result['imported'] );
		$this->assertSame( 1, $result['duplicates'] );
		$this->assertCount( 1, $this->meetings( 'Regular Meeting' ) );
	}

	/**
	 * Header case doesn't matter, a spaced "start time" header is simply not a
	 * recognised column (and costs nothing), and a BOM on the first header
	 * doesn't stop later time columns being read.
	 */
	public function test_header_handling(): void {
		$this->import(
			[ 'Title', 'DATE', 'Start_Time' ],
			[ [ 'Case Meeting', '2024-03-15', '5pm' ] ]
		);
		$this->assertSame( '17:00', get_post_meta( $this->meetings( 'Case Meeting' )[0]->ID, MeetingTime::META_START, true ) );

		$result = $this->import(
			[ 'title', 'date', 'start time' ],
			[ [ 'Spaced Header', '2024-03-15', '5pm' ] ]
		);
		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['invalid_times'] );
		$this->assertFalse( metadata_exists( 'post', $this->meetings( 'Spaced Header' )[0]->ID, MeetingTime::META_START ) );

		$this->import(
			[ 'title', 'date', 'start_time' ],
			[ [ 'Bom Meeting', '2024-03-15', '5pm' ] ],
			"\xEF\xBB\xBF"
		);
		$this->assertSame( '17:00', get_post_meta( $this->meetings( 'Bom Meeting' )[0]->ID, MeetingTime::META_START, true ) );
	}

	/**
	 * A meeting marked not held keeps the time it was given (the front end
	 * simply doesn't show it).
	 */
	public function test_not_held_row_keeps_its_time(): void {
		$this->import(
			[ 'title', 'date', 'not_held', 'start_time' ],
			[ [ 'Cancelled', '2024-03-15', 'yes', '5pm' ] ]
		);

		$this->assertSame( '17:00', get_post_meta( $this->meetings( 'Cancelled' )[0]->ID, MeetingTime::META_START, true ) );
	}

	/**
	 * The results notice mentions unreadable times only when there were some.
	 */
	public function test_status_message_reports_invalid_times(): void {
		$method = new \ReflectionMethod( CsvImporter::class, 'resolve_status_message' );
		$method->setAccessible( true );

		$_GET['edbs_import_success'] = '3';
		$plain                       = $method->invoke( new CsvImporter() )['message'];
		$this->assertStringNotContainsString( 'could not be read', $plain );

		$_GET['edbs_import_invalid_times'] = '2';
		$message                          = $method->invoke( new CsvImporter() )['message'];
		$this->assertStringContainsString( '2 rows had a start or end time that could not be read', $message );
		$this->assertStringStartsWith( $plain, $message );
	}

	/**
	 * The duplicate notice names the start time, like the import page's copy.
	 */
	public function test_status_message_duplicate_notice_mentions_the_start_time(): void {
		$method = new \ReflectionMethod( CsvImporter::class, 'resolve_status_message' );
		$method->setAccessible( true );

		$_GET['edbs_import_success']    = '1';
		$_GET['edbs_import_duplicates'] = '2';

		$message = $method->invoke( new CsvImporter() )['message'];

		unset( $_GET['edbs_import_duplicates'] );

		$this->assertStringContainsString( 'a matching or absent start time', $message );
	}
}
