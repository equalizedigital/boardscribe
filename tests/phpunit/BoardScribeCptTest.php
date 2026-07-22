<?php
/**
 * Tests for PostType\BoardScribeCPT::register_post_type().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\PostType\BoardScribeCPT;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the CPT's registered args (the intentionally-documented
 * public/show_in_rest combination) and the edbs_cpt_args filter added
 * so Pro or a site can further customize registration — including
 * opting into 'public' => true for plugins like ArchiveWP that key
 * their own features off that flag (see PRO-1209/GH#25), paused as the
 * default for now.
 */
class BoardScribeCptTest extends TestCase {

	/**
	 * The edbs_meetings post type is registered.
	 */
	public function test_cpt_is_registered(): void {
		$this->assertTrue( post_type_exists( 'edbs_meetings' ) );
	}

	/**
	 * The CPT's registered args match the documented, intentional
	 * defaults (public => false but show_in_rest => true).
	 */
	public function test_cpt_has_documented_default_args(): void {
		$post_type_object = get_post_type_object( 'edbs_meetings' );

		$this->assertFalse( $post_type_object->public );
		$this->assertTrue( $post_type_object->show_in_rest );
		$this->assertFalse( $post_type_object->has_archive );
		$this->assertTrue( post_type_supports( 'edbs_meetings', 'title' ) );
		$this->assertFalse( post_type_supports( 'edbs_meetings', 'editor' ) );
	}

	/**
	 * The edbs_cpt_args filter receives the registration args before
	 * register_post_type() is called, so Pro or a site can modify them
	 * (e.g. to enable public/rewrite/archive support for an approval
	 * workflow, archive page, or ArchiveWP compatibility).
	 */
	public function test_edbs_cpt_args_filter_receives_registration_args(): void {
		$captured_args = null;
		$callback      = static function ( array $args ) use ( &$captured_args ): array {
			$captured_args = $args;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		unregister_post_type( 'edbs_meetings' );
		( new BoardScribeCPT() )->register_post_type();

		remove_filter( 'edbs_cpt_args', $callback );

		$this->assertIsArray( $captured_args );
		$this->assertArrayHasKey( 'public', $captured_args );
		$this->assertFalse( $captured_args['public'] );
	}

	/**
	 * A callback on edbs_cpt_args can actually change the registered
	 * CPT's behavior, not just observe the args.
	 */
	public function test_edbs_cpt_args_filter_can_change_registration(): void {
		$callback = static function ( array $args ): array {
			$args['public'] = true;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		try {
			unregister_post_type( 'edbs_meetings' );
			( new BoardScribeCPT() )->register_post_type();

			$post_type_object = get_post_type_object( 'edbs_meetings' );
			$this->assertTrue( $post_type_object->public );
		} finally {
			// Restore the CPT to its normal registration for subsequent
			// tests, even if the assertion above failed.
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_meetings' );
			( new BoardScribeCPT() )->register_post_type();
		}
	}

	/**
	 * A site or Pro can also opt into a real archive page via the filter,
	 * since rewrite/has_archive default to false rather than being locked.
	 */
	public function test_edbs_cpt_args_filter_can_enable_rewrite_and_archive(): void {
		$callback = static function ( array $args ): array {
			$args['rewrite']     = true;
			$args['has_archive'] = true;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		try {
			unregister_post_type( 'edbs_meetings' );
			( new BoardScribeCPT() )->register_post_type();

			$post_type_object = get_post_type_object( 'edbs_meetings' );
			$this->assertNotFalse( $post_type_object->rewrite );
			$this->assertTrue( $post_type_object->has_archive );
		} finally {
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_meetings' );
			( new BoardScribeCPT() )->register_post_type();
		}
	}

	/**
	 * 'public' is paused back to false for now, so the CPT does not show
	 * up in a get_post_types(['public' => true]) query by default — the
	 * exact check third-party plugins like ArchiveWP use to build their
	 * archivable-post-types list (GH#25 / PRO-1209).
	 */
	public function test_cpt_is_not_included_in_public_post_types_query_by_default(): void {
		$public_post_types = get_post_types( [ 'public' => true ], 'names' );

		$this->assertNotContains( 'edbs_meetings', $public_post_types );
	}

	/**
	 * A site or Pro can opt back into ArchiveWP compatibility (GH#25 /
	 * PRO-1209) via the edbs_cpt_args filter, since 'public' is only
	 * paused as the default, not removed as an option.
	 */
	public function test_edbs_cpt_args_filter_can_restore_public_post_types_query_inclusion(): void {
		$callback = static function ( array $args ): array {
			$args['public'] = true;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		try {
			unregister_post_type( 'edbs_meetings' );
			( new BoardScribeCPT() )->register_post_type();

			$public_post_types = get_post_types( [ 'public' => true ], 'names' );
			$this->assertContains( 'edbs_meetings', $public_post_types );
		} finally {
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_meetings' );
			( new BoardScribeCPT() )->register_post_type();
		}
	}
}
