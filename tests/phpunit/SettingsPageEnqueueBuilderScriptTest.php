<?php
/**
 * Tests for SettingsPage::enqueue_assets().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\SettingsPage;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the settings page's asset enqueue: the settings stylesheet loads on
 * every settings tab, while the Shortcode Builder bundle + wp-components/
 * builder.css only load on the builder tab. The field registry is localized
 * in the shape the React app expects, and the frontend pipeline (for the live
 * preview) is pulled in alongside it - including edbs_enqueue_assets firing,
 * which is what lets Pro's columns/templates render in the preview.
 */
class SettingsPageEnqueueBuilderScriptTest extends TestCase {

	const SETTINGS_HOOK = 'edbs_meeting_page_edbs-settings';

	/**
	 * The SettingsPage instance under test.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Resets the global script/style queues so assertions only see
	 * what this test's own call enqueued.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->settings_page = new SettingsPage();

		global $wp_scripts, $wp_styles;
		$wp_scripts = new \WP_Scripts();
		$wp_styles  = new \WP_Styles();
	}

	/**
	 * Clears the tab request var between tests.
	 */
	public function tear_down(): void {
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	/**
	 * On any admin page other than the settings page, nothing is enqueued -
	 * neither the settings styles nor the builder bundle have any business
	 * loading elsewhere in wp-admin.
	 */
	public function test_does_nothing_on_a_different_admin_page(): void {
		$this->settings_page->enqueue_assets( 'edit.php' );

		$this->assertFalse( wp_style_is( 'edbs-settings', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'edbs-shortcode-builder', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'edbs-boardscribe', 'enqueued' ) );
	}

	/**
	 * On a non-builder settings tab, the settings stylesheet loads but the
	 * builder bundle and frontend pipeline stay off - they belong to the
	 * builder tab only.
	 */
	public function test_enqueues_only_settings_style_on_non_builder_tab(): void {
		$_GET['tab'] = 'general';

		$this->settings_page->enqueue_assets( self::SETTINGS_HOOK );

		$this->assertTrue( wp_style_is( 'edbs-settings', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'edbs-shortcode-builder', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'edbs-boardscribe', 'enqueued' ) );
	}

	/**
	 * On the builder tab, both the builder bundle and the frontend pipeline
	 * (for the live preview) are enqueued, along with the wp-components
	 * styles the React app's controls need.
	 */
	public function test_enqueues_builder_and_frontend_assets_on_the_builder_tab(): void {
		$_GET['tab'] = 'builder';

		$this->settings_page->enqueue_assets( self::SETTINGS_HOOK );

		$this->assertTrue( wp_style_is( 'edbs-settings', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'edbs-shortcode-builder', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wp-components', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'edbs-shortcode-builder', 'enqueued' ) );

		// The live preview runs the real frontend pipeline - proves
		// enqueue_assets() actually calls BoardScribeShortcode's (now
		// public) enqueue_assets(), not just the builder's own bundle.
		$this->assertTrue( wp_script_is( 'edbs-boardscribe', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'edbs-boardscribe', 'enqueued' ) );
	}

	/**
	 * The localized edbsBuilderFieldRegistry data is exactly
	 * FieldRegistry::js_schema() - the same contract the block editor's
	 * window.edbsBlockFieldRegistry relies on, just under the builder's
	 * own global name.
	 */
	public function test_localizes_the_field_registry_schema(): void {
		$_GET['tab'] = 'builder';

		$this->settings_page->enqueue_assets( self::SETTINGS_HOOK );

		$localized = wp_scripts()->get_data( 'edbs-shortcode-builder', 'data' );
		$this->assertIsString( $localized );

		// wp_localize_script's inline data is `var edbsBuilderFieldRegistry = {...};`;
		// decode the JSON object literal back out to compare against the
		// source schema rather than string-matching the JS statement.
		preg_match( '/edbsBuilderFieldRegistry\s*=\s*(\[.*\]|\{.*\});/s', $localized, $matches );
		$this->assertNotEmpty( $matches, 'Expected edbsBuilderFieldRegistry to be localized.' );

		$decoded = json_decode( $matches[1], true );
		$this->assertSame( FieldRegistry::js_schema(), $decoded );
	}

	/**
	 * edbs_enqueue_assets fires on the builder tab too (via the shared
	 * enqueue_assets() call) - documented in HOOK-CONTRACT-CHANGES.md as
	 * a behavior change Pro callbacks must tolerate.
	 */
	public function test_fires_edbs_enqueue_assets_action(): void {
		$_GET['tab'] = 'builder';

		$fired = false;
		add_action(
			'edbs_enqueue_assets',
			static function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->settings_page->enqueue_assets( self::SETTINGS_HOOK );

		$this->assertTrue( $fired );
	}
}
