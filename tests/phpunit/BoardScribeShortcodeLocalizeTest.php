<?php
/**
 * Tests for BoardScribeShortcode's edbsConfig localization (PRO-1413).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * The block editor preloads the post over REST (block_editor_rest_api_preload()),
 * which renders the block and so runs enqueue_assets(), then restores the
 * previous $wp_scripts. Anything localized during that preload is discarded,
 * so the localization must be decided by whether the current script object
 * actually has the data, not by a flag that outlives the restore.
 */
class BoardScribeShortcodeLocalizeTest extends TestCase {

	/**
	 * The scripts object the test started with, restored in tear_down().
	 *
	 * @var \WP_Scripts|null
	 */
	private $original_scripts;

	/**
	 * Saves the global scripts object.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_scripts;
		$this->original_scripts = $wp_scripts;
	}

	/**
	 * Restores the global scripts object.
	 */
	public function tear_down(): void {
		global $wp_scripts;
		$wp_scripts = $this->original_scripts;
		parent::tear_down();
	}

	/**
	 * A fresh scripts object (as after core restores its backup) must get
	 * edbsConfig even though enqueue_assets() already ran once before.
	 */
	public function test_edbs_config_is_attached_after_the_scripts_object_is_replaced(): void {
		global $wp_scripts;

		( new BoardScribeShortcode() )->enqueue_assets();
		$this->assertStringContainsString( 'edbsConfig', (string) wp_scripts()->get_data( 'edbs-boardscribe', 'data' ) );

		// Core's block_editor_rest_api_preload() restores the scripts object it
		// backed up before the preload, discarding what the preload localized.
		$wp_scripts = new \WP_Scripts();

		( new BoardScribeShortcode() )->enqueue_assets();

		$this->assertStringContainsString( 'edbsConfig', (string) wp_scripts()->get_data( 'edbs-boardscribe', 'data' ) );
	}

	/**
	 * Calling enqueue_assets() repeatedly on the same scripts object must not
	 * localize twice (the front end can call it once per shortcode on a page).
	 */
	public function test_edbs_config_is_not_duplicated_on_repeat_calls(): void {
		$shortcode = new BoardScribeShortcode();
		$shortcode->enqueue_assets();
		$shortcode->enqueue_assets();

		$this->assertSame(
			1,
			substr_count( (string) wp_scripts()->get_data( 'edbs-boardscribe', 'data' ), 'var edbsConfig' )
		);
	}
}
