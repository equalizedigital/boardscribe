<?php
/**
 * Tests for the re-import note on the CSV import page.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Import\CsvImporter;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Re-importing matches on each meeting's saved title, date and start time, so
 * the import page tells people which fields to leave alone.
 */
class CsvImporterPageNoteTest extends TestCase {

	/**
	 * The page explains what a re-import matches on and what editing those
	 * fields does.
	 */
	public function test_page_explains_how_re_imports_match_meetings(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		ob_start();
		( new CsvImporter() )->render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Importing a file again', $html );
		$this->assertStringContainsString( 'either the same start time or no start time', $html );
		$this->assertStringContainsString( 'A row without a start time is skipped when any saved meeting has the same title and date', $html );
		$this->assertStringContainsString( 'change its start time to a different time', $html );
		$this->assertStringContainsString( 'creates a new meeting', $html );
	}
}
