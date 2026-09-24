<?php
/**
 * Tests for the block editor canvas stylesheet (PRO-1422).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Block\BoardScribeBlock;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * The editor canvas is an iframe that only receives assets enqueued on
 * enqueue_block_assets (or declared on the block type), so the plugin's
 * stylesheet enqueued on enqueue_block_editor_assets never reached it and the
 * live preview rendered unstyled. It must be added on enqueue_block_assets -
 * in the admin only, since the front end already enqueues it with the block.
 */
class BoardScribeBlockEditorStylesTest extends TestCase {

	/**
	 * Registers the block's hooks and starts each test with the stylesheet
	 * unregistered.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_dequeue_style( 'edbs-boardscribe' );
		wp_deregister_style( 'edbs-boardscribe' );

		( new BoardScribeBlock() )->register();
	}

	/**
	 * Restores the front-end screen.
	 */
	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * In the admin (where the editor canvas assets are built), the stylesheet
	 * is enqueued on enqueue_block_assets.
	 */
	public function test_stylesheet_is_enqueued_for_the_editor_canvas(): void {
		set_current_screen( 'edit-post' );

		do_action( 'enqueue_block_assets' );

		$this->assertTrue( wp_style_is( 'edbs-boardscribe', 'enqueued' ) );
	}

	/**
	 * On the front end enqueue_block_assets must not add it - the block's own
	 * render already does, and a page without the block shouldn't load it.
	 */
	public function test_stylesheet_is_not_enqueued_on_the_front_end(): void {
		set_current_screen( 'front' );

		do_action( 'enqueue_block_assets' );

		$this->assertFalse( wp_style_is( 'edbs-boardscribe', 'enqueued' ) );
	}
}
