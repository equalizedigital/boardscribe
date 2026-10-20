<?php
/**
 * Tests for the CSV import notes hook (PRO-1514).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Import\CsvImporter;
use EqualizeDigital\BoardScribe\Import\ImportNotes;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * An add-on counts extra per-row results with ImportNotes (third argument of
 * edbs_csv_import_row_meta), registers wording for them on
 * edbs_csv_import_notes, and the importer adds them to the results notice.
 */
class CsvImporterNotesTest extends TestCase {

	/**
	 * Path to the temp CSV created for the running test, if any.
	 *
	 * @var string|null
	 */
	private ?string $csv_path = null;

	/**
	 * Clears the query args and removes the temp CSV after each test.
	 */
	public function tear_down(): void {
		unset( $_GET['edbs_import_success'], $_GET['edbs_import_skipped'], $_GET['edbs_import_notes'] );

		if ( null !== $this->csv_path && file_exists( $this->csv_path ) ) {
			unlink( $this->csv_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		}
		$this->csv_path = null;

		parent::tear_down();
	}

	/**
	 * Registers wording for a "widgets" note.
	 */
	private function register_widgets_note(): void {
		add_filter(
			'edbs_csv_import_notes',
			static function ( array $notes ): array {
				$notes['widgets'] = [
					'singular' => '%d widget was linked.',
					'plural'   => '%d widgets were linked.',
				];
				return $notes;
			}
		);
	}

	/**
	 * Imports two rows through process_csv() (private, so by reflection).
	 *
	 * @return array
	 */
	private function process_two_rows(): array {
		$this->csv_path = tempnam( sys_get_temp_dir(), 'edbs-csv-notes-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam

		$handle = fopen( $this->csv_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $handle, [ 'title', 'date' ] );
		fputcsv( $handle, [ 'Notes One', '2026-04-01' ] );
		fputcsv( $handle, [ 'Notes Two', '2026-04-02' ] );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$method = new \ReflectionMethod( CsvImporter::class, 'process_csv' );
		$method->setAccessible( true );

		return $method->invoke( new CsvImporter(), $this->csv_path );
	}

	/**
	 * Calls a private CsvImporter method by reflection.
	 *
	 * @param string $name The method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function call( string $name, ...$args ) {
		$method = new \ReflectionMethod( CsvImporter::class, $name );
		$method->setAccessible( true );

		return $method->invoke( new CsvImporter(), ...$args );
	}

	/**
	 * The collector adds up counts per key.
	 */
	public function test_notes_count_per_key(): void {
		$notes = new ImportNotes();
		$notes->add( 'widgets' );
		$notes->add( 'widgets', 2 );
		$notes->add( 'gadgets' );

		$this->assertSame(
			[
				'widgets' => 3,
				'gadgets' => 1,
			],
			$notes->all()
		);
	}

	/**
	 * Empty keys and counts below one are ignored, and keys are sanitized.
	 */
	public function test_notes_ignore_empty_keys_and_non_positive_counts(): void {
		$notes = new ImportNotes();
		$notes->add( '' );
		$notes->add( 'widgets', 0 );
		$notes->add( 'widgets', -3 );
		$notes->add( 'Big Widgets!' );

		$this->assertSame( [ 'bigwidgets' => 1 ], $notes->all() );
	}

	/**
	 * A callback on edbs_csv_import_row_meta receives the collector as its third
	 * argument, and the totals come back on the result.
	 */
	public function test_row_meta_callback_receives_the_collector(): void {
		$callback = static function ( $post_id, $data, $notes ): void {
			if ( $notes instanceof ImportNotes ) {
				$notes->add( 'widgets' );
			}
		};
		add_action( 'edbs_csv_import_row_meta', $callback, 10, 3 );

		$result = $this->process_two_rows();

		remove_action( 'edbs_csv_import_row_meta', $callback, 10 );

		$this->assertSame( 2, $result['imported'] );
		$this->assertSame( [ 'widgets' => 2 ], $result['notes'] );
	}

	/**
	 * When nothing was noted, the result has no notes key (same shape as before).
	 */
	public function test_result_has_no_notes_key_when_nothing_was_noted(): void {
		$result = $this->process_two_rows();

		$this->assertArrayNotHasKey( 'notes', $result );
	}

	/**
	 * A callback registered with two accepted arguments keeps working.
	 */
	public function test_two_argument_callback_still_works(): void {
		$seen     = [];
		$callback = static function ( $post_id, $data ) use ( &$seen ): void {
			$seen[] = $data['title'];
		};
		add_action( 'edbs_csv_import_row_meta', $callback, 10, 2 );

		$result = $this->process_two_rows();

		remove_action( 'edbs_csv_import_row_meta', $callback, 10 );

		$this->assertSame( 2, $result['imported'] );
		$this->assertSame( [ 'Notes One', 'Notes Two' ], $seen );
	}

	/**
	 * Registered notes are added to the results notice, singular or plural.
	 */
	public function test_registered_notes_are_added_to_the_message(): void {
		$this->register_widgets_note();

		$_GET['edbs_import_success'] = '3';
		$_GET['edbs_import_notes']   = [ 'widgets' => '1' ];
		$one                         = $this->call( 'resolve_status_message' )['message'];

		$_GET['edbs_import_notes'] = [ 'widgets' => '4' ];
		$many                      = $this->call( 'resolve_status_message' )['message'];

		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped. 1 widget was linked.', $one );
		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped. 4 widgets were linked.', $many );
	}

	/**
	 * Unregistered keys, zero, non-numeric and nested values are ignored.
	 */
	public function test_unregistered_and_malformed_notes_are_ignored(): void {
		$this->register_widgets_note();

		$_GET['edbs_import_success'] = '3';
		$_GET['edbs_import_notes']   = [
			'evil'    => '5',
			'widgets' => '0',
			'gadgets' => 'abc',
			'nested'  => [ '1' ],
		];

		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped.', $this->call( 'resolve_status_message' )['message'] );

		$_GET['edbs_import_notes'] = 'not-an-array';

		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped.', $this->call( 'resolve_status_message' )['message'] );

		// Registered keys with non-scalar or non-numeric values: this is what the is_scalar() guard is for.
		$_GET['edbs_import_notes'] = [ 'widgets' => [ '1' ] ];

		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped.', $this->call( 'resolve_status_message' )['message'] );

		$_GET['edbs_import_notes'] = [ 'widgets' => 'abc' ];

		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped.', $this->call( 'resolve_status_message' )['message'] );
	}

	/**
	 * Without the query arg the message is exactly what it was before this hook.
	 */
	public function test_message_is_unchanged_without_notes(): void {
		$this->register_widgets_note();

		$_GET['edbs_import_success'] = '3';

		$this->assertSame( 'Import complete. 3 rows imported, 0 skipped.', $this->call( 'resolve_status_message' )['message'] );
	}

	/**
	 * Only registered keys with a positive count go into the redirect.
	 */
	public function test_redirect_arg_keeps_only_registered_positive_notes(): void {
		$this->register_widgets_note();

		$arg = $this->call(
			'notes_query_arg',
			[
				'widgets' => 2,
				'evil'    => 9,
			]
		);

		$this->assertSame( [ 'widgets' => 2 ], $arg );
		$this->assertSame( [], $this->call( 'notes_query_arg', [ 'evil' => 9 ] ) );
	}
}
