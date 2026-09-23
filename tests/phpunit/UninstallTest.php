<?php
/**
 * Tests for uninstall.php's opt-in data cleanup.
 *
 * @package EqualizeDigital\BoardScribe
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers uninstall.php directly (via include, guarded the same way WP
 * itself would run it) rather than duplicating its logic in a testable
 * method - it's a top-level script with no function/class to unit test in
 * isolation.
 *
 * Regression coverage for PRO-1393: the cleanup query used to run with
 * post_status => 'any', which core deliberately excludes trash and
 * auto-draft from (both are registered exclude_from_search => true) - so
 * a user opting into full data deletion still ended up with orphaned
 * trashed/never-finished meeting posts and their postmeta left behind.
 */
class UninstallTest extends TestCase {

	/**
	 * Removes the options uninstall.php itself deletes, so each test
	 * starts from a clean slate regardless of run order.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( 'edbs_settings' );
		delete_option( 'edbs_activation_date' );
	}

	/**
	 * Runs uninstall.php in-process, defining WP_UNINSTALL_PLUGIN the same
	 * way WordPress core does before including it for real.
	 *
	 * @return void
	 */
	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'boardscribe/boardscribe.php' );
		}

		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * With delete_on_uninstall left off (the default), no meeting posts
	 * are touched.
	 */
	public function test_does_nothing_when_not_opted_in(): void {
		$meeting_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		$this->run_uninstall();

		$this->assertInstanceOf( \WP_Post::class, get_post( $meeting_id ) );
	}

	/**
	 * Opting in deletes a normal published meeting, same as before.
	 */
	public function test_deletes_a_published_meeting_when_opted_in(): void {
		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$meeting_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		$this->run_uninstall();

		$this->assertNull( get_post( $meeting_id ) );
	}

	/**
	 * Opting in also deletes a trashed meeting - excluded by the old
	 * post_status => 'any' query, since core marks 'trash'
	 * exclude_from_search.
	 */
	public function test_deletes_a_trashed_meeting_when_opted_in(): void {
		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$meeting_id = self::factory()->post->create(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'trash',
			]
		);

		$this->run_uninstall();

		$this->assertNull( get_post( $meeting_id ) );
	}

	/**
	 * Opting in also deletes an auto-draft meeting (a never-finished
	 * autosave) - also excluded by 'any' for the same reason as trash.
	 */
	public function test_deletes_an_auto_draft_meeting_when_opted_in(): void {
		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$meeting_id = self::factory()->post->create(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'auto-draft',
			]
		);

		$this->run_uninstall();

		$this->assertNull( get_post( $meeting_id ) );
	}

	/**
	 * A post of a different post type is left alone even when opted in.
	 */
	public function test_does_not_touch_unrelated_post_types(): void {
		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$this->run_uninstall();

		$this->assertInstanceOf( \WP_Post::class, get_post( $page_id ) );
	}

	/**
	 * Opting in also removes the plugin's own options.
	 */
	public function test_deletes_plugin_options_when_opted_in(): void {
		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		update_option( 'edbs_activation_date', '2026-01-01' );

		$this->run_uninstall();

		$this->assertFalse( get_option( 'edbs_activation_date' ) );
	}
}
