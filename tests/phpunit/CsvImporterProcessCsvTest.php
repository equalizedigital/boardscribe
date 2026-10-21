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
	 * @param array<int, array{title?: string, date: string, publish_date?: string, agenda_url?: string, minutes_url?: string}> $rows Row data.
	 * @return array{imported: int, skipped: int, scheduled: int, duplicates: int, invalid_urls: int}
	 */
	private function process( array $rows ): array {
		$this->csv_path = tempnam( sys_get_temp_dir(), 'edbs-csv-test-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam

		$handle = fopen( $this->csv_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $handle, [ 'title', 'date', 'publish_date', 'agenda_url', 'minutes_url' ] );
		foreach ( $rows as $row ) {
			fputcsv( $handle, [ $row['title'] ?? '', $row['date'], $row['publish_date'] ?? '', $row['agenda_url'] ?? '', $row['minutes_url'] ?? '' ] );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'process_csv' );
		$method->setAccessible( true );

		return $method->invoke( $importer, $this->csv_path );
	}

	/**
	 * Finds an imported meeting by its exact title, in any status.
	 *
	 * @param string $title The meeting title.
	 * @return \WP_Post|null
	 */
	private function find_meeting( string $title ): ?\WP_Post {
		$posts = get_posts(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'any',
				'title'       => $title,
				'numberposts' => 1,
			]
		);

		return $posts[0] ?? null;
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
	/**
	 * Two identical rows in one file: the second matches the first just
	 * inserted, so only one meeting is created.
	 */
	public function test_identical_rows_within_one_file_are_deduplicated(): void {
		$result = $this->process(
			[
				[ 'title' => 'Special Meeting', 'date' => '2024-03-15' ],
				[ 'title' => 'Special Meeting', 'date' => '2024-03-15' ],
			]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['duplicates'] );
	}

	/**
	 * A title containing an ampersand re-imports as a duplicate rather than
	 * creating a second copy.
	 */
	public function test_title_with_special_characters_is_recognised_as_a_duplicate_on_reimport(): void {
		$first  = $this->process( [ [ 'title' => 'Parks & Rec', 'date' => '2024-03-15' ] ] );
		$second = $this->process( [ [ 'title' => 'Parks & Rec', 'date' => '2024-03-15' ] ] );

		$this->assertSame( 1, $first['imported'] );
		$this->assertSame( 0, $second['imported'] );
		$this->assertSame( 1, $second['duplicates'] );
	}

	/**
	 * PRO-1414: an invalid agenda/minutes URL doesn't discard the meeting - it
	 * is imported without that link, and the row is reported as having an
	 * invalid URL instead of silently saving esc_url_raw()'s encoded garbage.
	 */
	public function test_invalid_urls_are_not_saved_and_are_reported(): void {
		$result = $this->process(
			[
				[
					'title'       => 'Bad Links Meeting',
					'date'        => '2024-03-15',
					'agenda_url'  => 'http://not a valid url',
					'minutes_url' => 'https://exa mple.com/minutes.pdf',
				],
			]
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['invalid_urls'] );

		$post = $this->find_meeting( 'Bad Links Meeting' );
		$this->assertNotNull( $post );
		$this->assertSame( '', get_post_meta( $post->ID, 'edbs_agenda_url', true ) );
		$this->assertSame( '', get_post_meta( $post->ID, 'edbs_minutes_url', true ) );
	}

	/**
	 * PRO-1414: valid URLs are saved as before and aren't counted as invalid.
	 */
	public function test_valid_urls_are_saved_and_not_reported(): void {
		$result = $this->process(
			[
				[
					'title'       => 'Good Links Meeting',
					'date'        => '2024-03-16',
					'agenda_url'  => 'https://example.com/agenda.pdf',
					'minutes_url' => 'https://example.com/minutes.pdf',
				],
			]
		);

		$this->assertSame( 0, $result['invalid_urls'] );

		$post = $this->find_meeting( 'Good Links Meeting' );
		$this->assertSame( 'https://example.com/agenda.pdf', get_post_meta( $post->ID, 'edbs_agenda_url', true ) );
		$this->assertSame( 'https://example.com/minutes.pdf', get_post_meta( $post->ID, 'edbs_minutes_url', true ) );
	}

	/**
	 * PRO-1414: one bad URL keeps the good one on the same row, and a row is
	 * counted once however many of its URLs are invalid.
	 */
	public function test_one_bad_url_keeps_the_good_one_and_counts_the_row_once(): void {
		$result = $this->process(
			[
				[
					'title'       => 'Mixed Links Meeting',
					'date'        => '2024-03-17',
					'agenda_url'  => 'http://not a valid url',
					'minutes_url' => 'https://example.com/minutes.pdf',
				],
			]
		);

		$this->assertSame( 1, $result['invalid_urls'] );

		$post = $this->find_meeting( 'Mixed Links Meeting' );
		$this->assertSame( '', get_post_meta( $post->ID, 'edbs_agenda_url', true ) );
		$this->assertSame( 'https://example.com/minutes.pdf', get_post_meta( $post->ID, 'edbs_minutes_url', true ) );
	}

	/**
	 * PRO-1414: an add-on's own URL columns can be added through the
	 * edbs_csv_import_url_columns filter and are validated and counted with
	 * the same rule. The column here is read back through the row-meta action,
	 * as an add-on would save it.
	 */
	public function test_filtered_url_columns_are_validated_and_counted(): void {
		add_filter(
			'edbs_csv_import_url_columns',
			static function ( array $columns ): array {
				$columns[] = 'livestream_url';
				return $columns;
			}
		);

		$this->csv_path = tempnam( sys_get_temp_dir(), 'edbs-csv-test-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam
		file_put_contents( $this->csv_path, "title,date,livestream_url\nFiltered Col Meeting,2024-04-01,http://not a valid url\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$method = new \ReflectionMethod( CsvImporter::class, 'process_csv' );
		$method->setAccessible( true );
		$result = $method->invoke( new CsvImporter(), $this->csv_path );

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['invalid_urls'] );
	}

	/**
	 * An invalid value in a filtered-in URL column is cleared from $data
	 * before edbs_csv_import_row_meta fires, not just excluded from the
	 * two columns this method saves itself - an add-on's own row-meta
	 * callback (e.g. Pro's) must never see the invalid raw value, even if
	 * it doesn't independently re-validate before saving.
	 */
	public function test_invalid_filtered_url_is_cleared_before_the_row_meta_action(): void {
		add_filter(
			'edbs_csv_import_url_columns',
			static function ( array $columns ): array {
				$columns[] = 'livestream_url';
				return $columns;
			}
		);

		$received = null;
		add_action(
			'edbs_csv_import_row_meta',
			static function ( int $post_id, array $data ) use ( &$received ) {
				unset( $post_id );
				$received = $data;
			},
			10,
			2
		);

		$this->csv_path = tempnam( sys_get_temp_dir(), 'edbs-csv-test-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam
		file_put_contents( $this->csv_path, "title,date,livestream_url\nCleared Col Meeting,2024-04-01,http://not a valid url\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$method = new \ReflectionMethod( CsvImporter::class, 'process_csv' );
		$method->setAccessible( true );
		$method->invoke( new CsvImporter(), $this->csv_path );

		$this->assertNotNull( $received );
		$this->assertSame( '', $received['livestream_url'] );
	}
}
