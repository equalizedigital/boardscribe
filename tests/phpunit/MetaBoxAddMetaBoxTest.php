<?php
/**
 * Tests for MetaBox::add_meta_box().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the edbs_use_native_meta_boxes filter, the documented
 * extension point that lets Pro (or another integration) fully
 * replace the native meta box UI.
 */
class MetaBoxAddMetaBoxTest extends TestCase {

	/**
	 * Clears any previously registered meta box for this screen so each
	 * test starts from a known state.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_meta_boxes;
		unset( $wp_meta_boxes['edbs_meeting'] );
	}

	/**
	 * By default (filter untouched), the native meta box is registered.
	 */
	public function test_meta_box_is_registered_by_default(): void {
		( new MetaBox() )->add_meta_box();

		global $wp_meta_boxes;
		$this->assertArrayHasKey( 'edbs_meeting_details', $wp_meta_boxes['edbs_meeting']['normal']['high'] );
	}

	/**
	 * Returning false from edbs_use_native_meta_boxes suppresses the
	 * native meta box entirely, so Pro can render its own instead.
	 */
	public function test_filter_can_suppress_native_meta_box(): void {
		add_filter( 'edbs_use_native_meta_boxes', '__return_false' );

		( new MetaBox() )->add_meta_box();

		remove_filter( 'edbs_use_native_meta_boxes', '__return_false' );

		global $wp_meta_boxes;
		$this->assertArrayNotHasKey( 'edbs_meeting', (array) $wp_meta_boxes );
	}
}
