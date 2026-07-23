<?php
/**
 * Tests for PostType\BoardScribeCPT::register_post_type().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\PostType\BoardScribeCPT;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the CPT's registered args (public with a real rewrite base,
 * show_in_rest, no archive) and the edbs_cpt_args filter that lets Pro
 * or a site customize registration further, including disabling
 * public/rewrite back off.
 */
class BoardScribeCptTest extends TestCase {

	/**
	 * The edbs_meeting post type is registered.
	 */
	public function test_cpt_is_registered(): void {
		$this->assertTrue( post_type_exists( 'edbs_meeting' ) );
	}

	/**
	 * The CPT's registered args match the documented, intentional
	 * defaults: public with a 'board-meetings' rewrite base (so
	 * third-party plugins like ArchiveWP that key their own features off
	 * the 'public' flag directly can see it, and meetings get a real
	 * single-page permalink), but no theme archive page.
	 */
	public function test_cpt_has_documented_default_args(): void {
		$post_type_object = get_post_type_object( 'edbs_meeting' );

		$this->assertTrue( $post_type_object->public );
		$this->assertIsArray( $post_type_object->rewrite );
		$this->assertSame( 'board-meetings', $post_type_object->rewrite['slug'] );
		$this->assertTrue( $post_type_object->show_in_rest );
		$this->assertFalse( $post_type_object->has_archive );
	}

	/**
	 * The CPT shows up in a get_post_types(['public' => true]) query by
	 * default - the exact check third-party plugins like ArchiveWP use
	 * to build their archivable-post-types list (GH#25 / PRO-1209).
	 */
	public function test_cpt_is_included_in_public_post_types_query(): void {
		$public_post_types = get_post_types( [ 'public' => true ], 'names' );

		$this->assertContains( 'edbs_meeting', $public_post_types );
	}

	/**
	 * The edbs_cpt_args filter receives the registration args before
	 * register_post_type() is called, so Pro or a site can modify them
	 * (e.g. to change the rewrite slug, enable a real archive page, or
	 * disable public/rewrite back to false).
	 */
	public function test_edbs_cpt_args_filter_receives_registration_args(): void {
		$captured_args = null;
		$callback      = static function ( array $args ) use ( &$captured_args ): array {
			$captured_args = $args;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		unregister_post_type( 'edbs_meeting' );
		( new BoardScribeCPT() )->register_post_type();

		remove_filter( 'edbs_cpt_args', $callback );

		$this->assertIsArray( $captured_args );
		$this->assertArrayHasKey( 'public', $captured_args );
		$this->assertTrue( $captured_args['public'] );
	}

	/**
	 * A callback on edbs_cpt_args can actually change the registered
	 * CPT's behavior, not just observe the args - demonstrated here by
	 * disabling public, the opposite of the new default.
	 */
	public function test_edbs_cpt_args_filter_can_change_registration(): void {
		$callback = static function ( array $args ): array {
			$args['public'] = false;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		try {
			unregister_post_type( 'edbs_meeting' );
			( new BoardScribeCPT() )->register_post_type();

			$post_type_object = get_post_type_object( 'edbs_meeting' );
			$this->assertFalse( $post_type_object->public );
		} finally {
			// Restore the CPT to its normal registration for subsequent
			// tests, even if the assertion above failed.
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_meeting' );
			( new BoardScribeCPT() )->register_post_type();
		}
	}

	/**
	 * A site or Pro can also opt into a real archive page via the filter,
	 * since has_archive defaults to false rather than being locked.
	 */
	public function test_edbs_cpt_args_filter_can_enable_archive(): void {
		$callback = static function ( array $args ): array {
			$args['has_archive'] = true;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		try {
			unregister_post_type( 'edbs_meeting' );
			( new BoardScribeCPT() )->register_post_type();

			$post_type_object = get_post_type_object( 'edbs_meeting' );
			$this->assertTrue( $post_type_object->has_archive );
		} finally {
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_meeting' );
			( new BoardScribeCPT() )->register_post_type();
		}
	}

	/**
	 * A site can change the rewrite slug via the filter without needing
	 * to disable rewrite entirely.
	 */
	public function test_edbs_cpt_args_filter_can_change_rewrite_slug(): void {
		$callback = static function ( array $args ): array {
			$args['rewrite'] = [ 'slug' => 'meetings' ];
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		try {
			unregister_post_type( 'edbs_meeting' );
			( new BoardScribeCPT() )->register_post_type();

			$post_type_object = get_post_type_object( 'edbs_meeting' );
			$this->assertSame( 'meetings', $post_type_object->rewrite['slug'] );
		} finally {
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_meeting' );
			( new BoardScribeCPT() )->register_post_type();
		}
	}
}
