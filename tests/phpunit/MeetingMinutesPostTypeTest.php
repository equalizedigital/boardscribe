<?php
/**
 * Tests for PostType\MeetingMinutes::register_post_type().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\PostType\MeetingMinutes as MeetingMinutesCPT;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the CPT's registered args (the intentionally-documented
 * public/show_in_rest combination) and the edbs_cpt_args filter added
 * so Pro can enable public/rewrite/archive support.
 */
class MeetingMinutesPostTypeTest extends TestCase {

	/**
	 * The edbs_boardscribe post type is registered.
	 */
	public function test_cpt_is_registered(): void {
		$this->assertTrue( post_type_exists( 'edbs_boardscribe' ) );
	}

	/**
	 * The CPT's registered args match the documented, intentional
	 * defaults (public => false but show_in_rest => true).
	 */
	public function test_cpt_has_documented_default_args(): void {
		$post_type_object = get_post_type_object( 'edbs_boardscribe' );

		$this->assertFalse( $post_type_object->public );
		$this->assertTrue( $post_type_object->show_in_rest );
		$this->assertFalse( $post_type_object->has_archive );
	}

	/**
	 * The edbs_cpt_args filter receives the registration args before
	 * register_post_type() is called, so Pro can modify them (e.g. to
	 * enable public/rewrite/archive support for an approval workflow or
	 * archive page).
	 */
	public function test_edbs_cpt_args_filter_receives_registration_args(): void {
		$captured_args = null;
		$callback      = static function ( array $args ) use ( &$captured_args ): array {
			$captured_args = $args;
			return $args;
		};
		add_filter( 'edbs_cpt_args', $callback );

		unregister_post_type( 'edbs_boardscribe' );
		( new MeetingMinutesCPT() )->register_post_type();

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
			unregister_post_type( 'edbs_boardscribe' );
			( new MeetingMinutesCPT() )->register_post_type();

			$post_type_object = get_post_type_object( 'edbs_boardscribe' );
			$this->assertTrue( $post_type_object->public );
		} finally {
			// Restore the CPT to its normal registration for subsequent
			// tests, even if the assertion above failed.
			remove_filter( 'edbs_cpt_args', $callback );
			unregister_post_type( 'edbs_boardscribe' );
			( new MeetingMinutesCPT() )->register_post_type();
		}
	}
}
