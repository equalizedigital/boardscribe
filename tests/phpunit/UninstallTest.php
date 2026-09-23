<?php
/**
 * Tests for uninstall.php's opt-in data cleanup.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\PostType\BoardScribeCPT;
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
	 * Opting in still deletes a meeting even when edbs_meeting isn't a
	 * registered post type at uninstall time - the real-world case this
	 * plugin's own bootstrap never runs during WP_UNINSTALL_PLUGIN, so a
	 * get_posts()/WP_Query-based approach (which silently returns nothing
	 * for an unregistered post type) would leave every meeting behind.
	 */
	public function test_deletes_a_meeting_when_post_type_is_unregistered(): void {
		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$meeting_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		try {
			$this->assertTrue( unregister_post_type( 'edbs_meeting' ) );
			$this->run_uninstall();
		} finally {
			( new BoardScribeCPT() )->register_post_type();
		}

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
	 * Opting in also removes a deleted meeting's term relationships and
	 * decrements the term's count, even for a taxonomy that isn't
	 * registered at uninstall time (the real-world case for any
	 * Pro-registered taxonomy, e.g. meeting category/type - Pro only
	 * registers those via this plugin's own edbs_after_register_cpt
	 * action, which never fires during WP_UNINSTALL_PLUGIN).
	 * wp_delete_post() alone would leave both behind, since
	 * get_object_taxonomies() returns nothing for an unregistered
	 * taxonomy.
	 */
	public function test_cleans_up_term_relationships_for_an_unregistered_taxonomy(): void {
		register_taxonomy( 'edbs_test_taxonomy', 'edbs_meeting' );
		$term = wp_insert_term( 'Test Term', 'edbs_test_taxonomy' );

		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$meeting_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		wp_set_object_terms( $meeting_id, [ $term['term_id'] ], 'edbs_test_taxonomy' );

		// Bump the term's count to a known value above 1, then unregister
		// the taxonomy entirely - matching the real scenario, where the
		// taxonomy never gets registered at all during uninstall, not
		// just where wp_set_object_terms() happened to skip updating it.
		wp_update_term_count_now( [ $term['term_taxonomy_id'] ], 'edbs_test_taxonomy' );
		$before = get_term( $term['term_id'], 'edbs_test_taxonomy' );
		$this->assertSame( 1, $before->count );
		unregister_taxonomy( 'edbs_test_taxonomy' );

		global $wpdb;
		$this->run_uninstall();

		$relationship_count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $meeting_id )
		);
		$this->assertSame( '0', $relationship_count );

		register_taxonomy( 'edbs_test_taxonomy', 'edbs_meeting' );
		$after = get_term( $term['term_id'], 'edbs_test_taxonomy' );
		$this->assertSame( 0, $after->count );
	}

	/**
	 * Deleting a trashed meeting removes its term relationship but does
	 * NOT decrement the term's count - core's default term-count callback
	 * (_update_post_term_count()) only ever counts 'publish' posts, so a
	 * trashed meeting was never counted in the first place. Decrementing
	 * anyway would under-count a term whose real count reflects other
	 * (non-meeting) usage entirely - simulated here with a fixed baseline
	 * rather than a second published meeting, since uninstall.php deletes
	 * every edbs_meeting post in one pass and a second meeting using the
	 * same term would be deleted (and correctly decrement it) in the same
	 * run, making the two cases indistinguishable.
	 */
	public function test_does_not_decrement_term_count_for_a_non_published_meeting(): void {
		register_taxonomy( 'edbs_test_taxonomy', 'edbs_meeting' );
		$term = wp_insert_term( 'Test Term', 'edbs_test_taxonomy' );

		update_option( 'edbs_settings', [ 'delete_on_uninstall' => 1 ] );
		$trashed_id = self::factory()->post->create(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => 'trash',
			]
		);
		// Creates the relationship row; wp_set_object_terms() also
		// triggers its own recalculation, which the fixed baseline below
		// intentionally overrides.
		wp_set_object_terms( $trashed_id, [ $term['term_id'] ], 'edbs_test_taxonomy' );

		global $wpdb;
		$wpdb->update( $wpdb->term_taxonomy, [ 'count' => 3 ], [ 'term_taxonomy_id' => $term['term_taxonomy_id'] ] );
		clean_term_cache( [ $term['term_id'] ], 'edbs_test_taxonomy' );
		unregister_taxonomy( 'edbs_test_taxonomy' );

		$this->run_uninstall();

		register_taxonomy( 'edbs_test_taxonomy', 'edbs_meeting' );
		$after = get_term( $term['term_id'], 'edbs_test_taxonomy' );
		$this->assertSame( 3, $after->count );
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
