<?php
/**
 * Tests for MetaBox::register_post_meta().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the registered meta schema (sanitize_callback per field) and,
 * most importantly, the auth_callback's object_id handling - this is
 * what Gemini Code Assist flagged as a regression risk when the
 * auth_callback was tightened from a blanket edit_posts check to a
 * post-specific edit_post check.
 */
class MetaBoxRegisterMetaTest extends TestCase {

	/**
	 * Re-registers the meta fields before each test - WP's per-test
	 * global reset can clear $wp_meta_keys between tests, and
	 * register_post_meta() is idempotent, so calling it directly here
	 * guarantees the schema is present regardless of bootstrap timing.
	 */
	public function set_up(): void {
		parent::set_up();
		( new MetaBox() )->register_post_meta();
	}

	/**
	 * Returns the registered meta args for one of the plugin's meta keys.
	 *
	 * @param string $meta_key The meta key.
	 * @return array
	 */
	private function get_meta_args( string $meta_key ): array {
		global $wp_meta_keys;
		return $wp_meta_keys['post']['edbs_boardscribe'][ $meta_key ];
	}

	/**
	 * All four meeting meta fields are registered as single, REST-visible
	 * fields on the CPT.
	 */
	public function test_all_meta_keys_are_registered(): void {
		global $wp_meta_keys;
		$keys = array_keys( $wp_meta_keys['post']['edbs_boardscribe'] );

		foreach ( [ 'edbs_meeting_date', 'edbs_agenda_url', 'edbs_minutes_url', 'edbs_meeting_not_held' ] as $expected_key ) {
			$this->assertContains( $expected_key, $keys );
		}
	}

	/**
	 * Text-type fields use sanitize_text_field.
	 */
	public function test_text_fields_use_sanitize_text_field(): void {
		$this->assertSame( 'sanitize_text_field', $this->get_meta_args( 'edbs_meeting_date' )['sanitize_callback'] );
		$this->assertSame( 'sanitize_text_field', $this->get_meta_args( 'edbs_meeting_not_held' )['sanitize_callback'] );
	}

	/**
	 * URL fields use esc_url_raw, matching the sanitization used again
	 * at save time in MetaBox::save_meta().
	 */
	public function test_url_fields_use_esc_url_raw(): void {
		$this->assertSame( 'esc_url_raw', $this->get_meta_args( 'edbs_agenda_url' )['sanitize_callback'] );
		$this->assertSame( 'esc_url_raw', $this->get_meta_args( 'edbs_minutes_url' )['sanitize_callback'] );
	}

	/**
	 * With a real post ID, the auth_callback checks edit_post capability
	 * for that specific post - a user who can't edit the post is denied
	 * even if they could edit posts of this type in general.
	 */
	public function test_auth_callback_denies_user_without_post_specific_capability(): void {
		$post_id       = self::factory()->post->create( [ 'post_type' => 'edbs_boardscribe' ] );
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$auth_callback = $this->get_meta_args( 'edbs_meeting_date' )['auth_callback'];
		$allowed       = $auth_callback( true, 'edbs_meeting_date', $post_id );

		wp_set_current_user( 0 );

		$this->assertFalse( $allowed );
	}

	/**
	 * With a real post ID, a user who can edit that post is allowed.
	 */
	public function test_auth_callback_allows_user_with_post_specific_capability(): void {
		$post_id   = self::factory()->post->create( [ 'post_type' => 'edbs_boardscribe' ] );
		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		$auth_callback = $this->get_meta_args( 'edbs_meeting_date' )['auth_callback'];
		$allowed       = $auth_callback( true, 'edbs_meeting_date', $post_id );

		wp_set_current_user( 0 );

		$this->assertTrue( $allowed );
	}

	/**
	 * With object_id 0 (e.g. authorizing meta for a post that doesn't
	 * exist yet, such as during new-post creation via the REST API),
	 * the auth_callback falls back to the general edit_posts capability
	 * instead of denying via current_user_can( 'edit_post', 0 ), which
	 * always returns false. This is the fix for the regression Gemini
	 * Code Assist flagged.
	 */
	public function test_auth_callback_falls_back_to_edit_posts_when_object_id_is_zero(): void {
		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		$auth_callback = $this->get_meta_args( 'edbs_meeting_date' )['auth_callback'];
		$allowed       = $auth_callback( true, 'edbs_meeting_date', 0 );

		wp_set_current_user( 0 );

		$this->assertTrue( $allowed );
	}

	/**
	 * A logged-out user is denied even with object_id 0.
	 */
	public function test_auth_callback_denies_logged_out_user_with_zero_object_id(): void {
		wp_set_current_user( 0 );

		$auth_callback = $this->get_meta_args( 'edbs_meeting_date' )['auth_callback'];
		$allowed       = $auth_callback( true, 'edbs_meeting_date', 0 );

		$this->assertFalse( $allowed );
	}
}
