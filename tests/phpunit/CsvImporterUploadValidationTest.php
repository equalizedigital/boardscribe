<?php
/**
 * Tests for CsvImporter::validate_upload() and is_allowed_csv_upload()
 * (PRO-1368).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Import\CsvImporter;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * handle_upload()'s own upload validation used to be thinner than its
 * comment claimed: no is_uploaded_file() check, $_FILES['error'] was never
 * read (a partial upload got processed as if complete), and the
 * mime_content_type() allow-list included 'text/plain', which that
 * function returns for nearly any text file - '.csv' was effectively
 * enforced only by the browser's own accept="" attribute.
 *
 * validate_upload() (extracted from handle_upload(), which calls exit() on
 * every path and so can't be exercised directly here) and its pure
 * is_allowed_csv_upload() helper now cover this.
 */
class CsvImporterUploadValidationTest extends TestCase {

	/**
	 * Calls the private validate_upload() method via reflection.
	 *
	 * @param array $file A $_FILES['edbs_csv']-shaped array.
	 * @return string|null
	 */
	private function validate( array $file ): ?string {
		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'validate_upload' );
		$method->setAccessible( true );

		return $method->invoke( $importer, $file );
	}

	/**
	 * Calls the private is_allowed_csv_upload() method via reflection.
	 *
	 * @param string $mime      Detected MIME type.
	 * @param string $extension Lowercased filename extension.
	 * @return bool
	 */
	private function allowed( string $mime, string $extension ): bool {
		$importer = new CsvImporter();
		$method   = new \ReflectionMethod( CsvImporter::class, 'is_allowed_csv_upload' );
		$method->setAccessible( true );

		return $method->invoke( $importer, $mime, $extension );
	}

	/**
	 * No file at all (empty/missing tmp_name) is rejected before anything
	 * else is checked.
	 */
	public function test_missing_tmp_name_is_rejected_as_no_file(): void {
		$this->assertSame( 'no_file', $this->validate( [] ) );
		$this->assertSame( 'no_file', $this->validate( [ 'tmp_name' => '' ] ) );
	}

	/**
	 * A non-OK $_FILES['error'] code (e.g. a partial/interrupted upload)
	 * is rejected even though tmp_name is non-empty - the gap the original
	 * review flagged: process_csv() would otherwise silently import
	 * whatever truncated rows made it through.
	 */
	public function test_non_ok_upload_error_code_is_rejected_even_with_a_tmp_name(): void {
		$result = $this->validate(
			[
				'tmp_name' => '/tmp/php_whatever',
				'error'    => UPLOAD_ERR_PARTIAL,
				'name'     => 'meetings.csv',
			]
		);

		$this->assertSame( 'upload_failed', $result );
	}

	/**
	 * A tmp_name that exists on disk but wasn't actually produced by this
	 * request's file upload (is_uploaded_file() returns false for it) is
	 * rejected, not silently processed - guards against a stale or
	 * attacker-guessed tmp path.
	 */
	public function test_tmp_name_not_recognized_as_a_real_upload_is_rejected(): void {
		$path = tempnam( sys_get_temp_dir(), 'edbs-csv-test-' );
		file_put_contents( $path, "title,date\nExample,2024-03-15\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture, not plugin runtime code.

		$result = $this->validate(
			[
				'tmp_name' => $path,
				'error'    => UPLOAD_ERR_OK,
				'name'     => 'meetings.csv',
			]
		);

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- test fixture cleanup, not plugin runtime code.

		// is_uploaded_file() is never true for a file this test wrote
		// itself (not a real PHP upload), so this always exercises the
		// rejection branch - there's no way to fake a passing
		// is_uploaded_file() result from PHPUnit.
		$this->assertSame( 'invalid_type', $result );
	}

	/**
	 * A .csv-extensioned file with each allow-listed MIME type is
	 * accepted.
	 *
	 * @dataProvider allowed_mime_provider
	 *
	 * @param string $mime Detected MIME type.
	 */
	public function test_allow_listed_mime_with_csv_extension_is_accepted( string $mime ): void {
		$this->assertTrue( $this->allowed( $mime, 'csv' ) );
	}

	/**
	 * Data provider for test_allow_listed_mime_with_csv_extension_is_accepted().
	 *
	 * @return array<string, array{0: string}>
	 */
	public function allowed_mime_provider(): array {
		return [
			'text/csv'                => [ 'text/csv' ],
			'text/plain'              => [ 'text/plain' ],
			'application/csv'         => [ 'application/csv' ],
			'application/vnd.ms-excel' => [ 'application/vnd.ms-excel' ],
		];
	}

	/**
	 * A file detected as text/plain - the permissive MIME type most
	 * plain-text files (not just CSVs) get detected as - is rejected once
	 * its extension isn't .csv, closing the gap the original review
	 * flagged (extension was previously not checked at all).
	 */
	public function test_text_plain_mime_with_a_non_csv_extension_is_rejected(): void {
		$this->assertFalse( $this->allowed( 'text/plain', 'txt' ) );
	}

	/**
	 * A disallowed MIME type is rejected even with a .csv extension - the
	 * extension check tightens the existing MIME allow-list, it doesn't
	 * replace it.
	 */
	public function test_disallowed_mime_with_csv_extension_is_still_rejected(): void {
		$this->assertFalse( $this->allowed( 'application/pdf', 'csv' ) );
	}
}
