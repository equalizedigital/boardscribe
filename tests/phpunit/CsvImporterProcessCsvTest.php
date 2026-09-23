<?php
/**
 * Tests for CsvImporter::process_csv() (PRO-1396).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Import\CsvImporter;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers two process_csv() behaviors from the 2026-09-23 audit: a future
 * publish_date silently turns wp_insert_post()'s 'publish' status into
 * 'future' (the row is scheduled, not live) and must be reported separately
 * rather than folded into the "N rows imported" count; and re-importing the
 * same file (e.g. after a timed-out run stopped partway through) must not
 * duplicate a row whose (title, date) already exists.
 */
class CsvImporterProcessCsvTest extends TestCase {

	/**
	 * Path to the temp CSV file created for the running test, if any.
	 *
	 * @var string|null
	 */
	private ?string $csv_path = null;

	/**
	 * Removes the temp CSV file after each test.
	 */
	public function tear_down(): void {
		if ( null !== $this->csv_path && file_exists( $this->csv_path ) ) {
			unlink( $this->csv_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		}
		$this->csv_path = null;

		parent::tear_down();
	}

	/**
	 * Writes $rows (each a title/date/publish_date tuple) to a temp CSV file
	 * and returns process_csv()'s result via reflection - no public entry
	 * point isolates this from the full upload/file-handling flow.
	 *
	 * @param array<int, array{title?: string, date: string, publish_date?: string}> $rows Row data.
	 * @return array{imported: int, skipped: int, scheduled: int, duplicates: int}
	 */
	private function process( array $rows ): array {
		$this->csv_path = tempnam( sys_get_temp_dir(), 'edbs-csv-test-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam

		$handle = fopen( $this->csv_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $handle, [ 'title', 'date', 'publish_date' ] );
		foreach ( $rows as $row ) {
			fputcsv( $handle, [ $row['title'] ?? '', $row['date'], $row['publish_date'] ?? '' ] );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'process_csv' );
		$method->setAccessible( true );

		return $method->invoke( $importer, $this->csv_path );
	}

	/**
	 * A row with no publish_date (or a past one) imports normally and isn't
	 * counted as scheduled.
	 */
	public function test_row_without_future_publish_date_is_imported_not_scheduled(): void {
		$result = $this->process(
			[
				[
					'title' => 'Regular Meeting',
					'date'  => '2024-03-15',
				],
			]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['scheduled'] );
	}

	/**
	 * A row whose publish_date is in the future is inserted (so it isn't
	 * lost) but wp_insert_post() downgrades 'publish' to 'future' - counted
	 * as scheduled, not imported, since it won't appear on the site yet.
	 */
	public function test_row_with_future_publish_date_is_scheduled_not_imported(): void {
		$future = gmdate( 'Y-m-d', strtotime( '+1 year' ) );

		$result = $this->process(
			[
				[
					'title'        => 'Future Meeting',
					'date'         => '2024-03-15',
					'publish_date' => $future,
				],
			]
		);

		$this->assertSame( 0, $result['imported'] );
		$this->assertSame( 1, $result['scheduled'] );

		$posts = get_posts(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'future',
				'title'       => 'Future Meeting',
			]
		);
		$this->assertCount( 1, $posts );
	}

	/**
	 * A row matching an already-existing meeting's title and date is
	 * skipped as a duplicate rather than creating a second post - covers
	 * re-uploading the same CSV after a partial/timed-out import.
	 */
	public function test_duplicate_title_and_date_is_skipped(): void {
		self::factory()->post->create(
			[
				'post_type'  => 'edbs_meeting',
				'post_title' => 'March Board Meeting',
				'meta_input' => [ 'edbs_meeting_date' => '2024-03-15' ],
			]
		);

		$result = $this->process(
			[
				[
					'title' => 'March Board Meeting',
					'date'  => '2024-03-15',
				],
			]
		);

		$this->assertSame( 0, $result['imported'] );
		$this->assertSame( 1, $result['duplicates'] );

		$posts = get_posts(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'any',
				'title'       => 'March Board Meeting',
			]
		);
		$this->assertCount( 1, $posts );
	}

	/**
	 * A same-titled meeting on a different date is not treated as a
	 * duplicate - (title, date) must both match.
	 */
	public function test_same_title_different_date_is_not_a_duplicate(): void {
		self::factory()->post->create(
			[
				'post_type'  => 'edbs_meeting',
				'post_title' => 'Recurring Meeting',
				'meta_input' => [ 'edbs_meeting_date' => '2024-01-01' ],
			]
		);

		$result = $this->process(
			[
				[
					'title' => 'Recurring Meeting',
					'date'  => '2024-03-15',
				],
			]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['duplicates'] );
	}
}
