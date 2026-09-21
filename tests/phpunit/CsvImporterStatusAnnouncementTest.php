<?php
/**
 * Tests for CsvImporter::resolve_status_message() and
 * announce_status_message() (PRO-1325).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Import\CsvImporter;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers resolving the import page's status message from its own redirect
 * query args, and announcing it to screen readers via wp.a11y.speak() -
 * see announce_status_message()'s own docblock for why the visible notice
 * markup alone isn't reliably announced, and why this doesn't also mark
 * that notice as a live region.
 */
class CsvImporterStatusAnnouncementTest extends TestCase {

	/**
	 * Clears the query args and any queued wp-a11y inline scripts before
	 * each test, so one test's $_GET/enqueue state can't leak into another.
	 */
	public function set_up(): void {
		parent::set_up();

		unset( $_GET['edbs_import_success'], $_GET['edbs_import_skipped'], $_GET['edbs_import_error'] );
		wp_scripts()->remove( 'wp-a11y' );
		wp_scripts()->add( 'wp-a11y', false );
	}

	/**
	 * Calls the private resolve_status_message() method via reflection.
	 *
	 * @return array{message: string, type: string}|null
	 */
	private function resolve(): ?array {
		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'resolve_status_message' );
		$method->setAccessible( true );

		return $method->invoke( $importer );
	}

	/**
	 * Calls the private announce_status_message() method via reflection.
	 *
	 * @param string $message The message to announce.
	 * @param string $type    'success' or 'error'.
	 * @return void
	 */
	private function announce( string $message, string $type ): void {
		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'announce_status_message' );
		$method->setAccessible( true );

		$method->invoke( $importer, $message, $type );
	}

	/**
	 * With neither query arg present, resolve_status_message() returns null.
	 */
	public function test_resolve_returns_null_with_no_query_args(): void {
		$this->assertNull( $this->resolve() );
	}

	/**
	 * A success redirect resolves to the same "N rows imported, M skipped"
	 * text the visible notice has always shown.
	 */
	public function test_resolve_returns_success_message(): void {
		$_GET['edbs_import_success'] = '3';
		$_GET['edbs_import_skipped'] = '1';

		$result = $this->resolve();

		$this->assertSame( 'success', $result['type'] );
		$this->assertSame( 'Import complete. 3 rows imported, 1 skipped.', $result['message'] );
	}

	/**
	 * A recognised error code resolves to its specific message.
	 */
	public function test_resolve_returns_known_error_message(): void {
		$_GET['edbs_import_error'] = 'invalid_type';

		$result = $this->resolve();

		$this->assertSame( 'error', $result['type'] );
		$this->assertSame( 'Invalid file type. Please upload a .csv file.', $result['message'] );
	}

	/**
	 * An unrecognised error code falls back to a generic message rather
	 * than exposing the raw code or erroring.
	 */
	public function test_resolve_returns_fallback_for_unknown_error_code(): void {
		$_GET['edbs_import_error'] = 'something_unexpected';

		$result = $this->resolve();

		$this->assertSame( 'error', $result['type'] );
		$this->assertSame( 'An unknown error occurred.', $result['message'] );
	}

	/**
	 * A success announcement is queued as a polite wp.a11y.speak() call on
	 * the wp-a11y handle, carrying the exact message text.
	 */
	public function test_announce_success_is_polite(): void {
		$this->announce( 'Import complete. 3 rows imported, 0 skipped.', 'success' );

		$inline = implode( ' ', wp_scripts()->get_data( 'wp-a11y', 'after' ) );

		$this->assertStringContainsString( 'wp.a11y.speak(', $inline );
		$this->assertStringContainsString( '"Import complete. 3 rows imported, 0 skipped."', $inline );
		$this->assertStringContainsString( '"polite"', $inline );
		$this->assertStringNotContainsString( '"assertive"', $inline );
	}

	/**
	 * An error announcement is queued as an assertive wp.a11y.speak() call -
	 * an error is announced immediately (interrupting), matching its higher
	 * urgency, rather than politely waiting its turn like a success message.
	 */
	public function test_announce_error_is_assertive(): void {
		$this->announce( 'An unknown error occurred.', 'error' );

		$inline = implode( ' ', wp_scripts()->get_data( 'wp-a11y', 'after' ) );

		$this->assertStringContainsString( 'wp.a11y.speak(', $inline );
		$this->assertStringContainsString( '"assertive"', $inline );
		$this->assertStringNotContainsString( '"polite"', $inline );
	}
}
