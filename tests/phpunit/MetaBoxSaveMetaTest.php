<?php
/**
 * Tests for MetaBox::save_meta().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the meta box save handler's nonce/capability gating and
 * per-field sanitization - this is the plugin's only direct-$_POST
 * write path, so it's the highest-value target for authorization and
 * sanitization regression coverage.
 */
class MetaBoxSaveMetaTest extends TestCase {

	/**
	 * The MetaBox instance under test.
	 *
	 * @var MetaBox
	 */
	private MetaBox $meta_box;

	/**
	 * The post ID used across tests.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Sets up a fresh MetaBox instance, test post, and valid POST/nonce
	 * baseline for each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->meta_box = new MetaBox();
		$this->post_id  = self::factory()->post->create( [ 'post_type' => 'edbs_boardscribe' ] );

		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		$_POST = [
			'edbs_meeting_meta_nonce' => wp_create_nonce( 'edbs_save_meeting_meta' ),
		];
	}

	/**
	 * Resets $_POST and the current user after each test.
	 */
	public function tear_down(): void {
		$_POST = [];
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Without the nonce field present at all, nothing is saved.
	 */
	public function test_missing_nonce_saves_nothing(): void {
		$_POST = [ 'edbs_meeting_date' => '2024-03-15' ];

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * An invalid/forged nonce is rejected.
	 */
	public function test_invalid_nonce_saves_nothing(): void {
		$_POST['edbs_meeting_meta_nonce'] = 'not-a-real-nonce';
		$_POST['edbs_meeting_date']       = '2024-03-15';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A valid nonce but a user without edit_post capability for this
	 * post is rejected.
	 *
	 * The nonce must be generated for the same user whose capability is
	 * under test (a subscriber, who can't edit this post) - reusing the
	 * editor's nonce from set_up() while switching to a different user
	 * would fail wp_verify_nonce() first, meaning the capability check
	 * this test claims to cover would never actually run.
	 */
	public function test_user_without_capability_saves_nothing(): void {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$_POST['edbs_meeting_meta_nonce'] = wp_create_nonce( 'edbs_save_meeting_meta' );
		$_POST['edbs_meeting_date']       = '2024-03-15';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A well-formed Y-m-d date is saved as-is.
	 */
	public function test_valid_date_is_saved(): void {
		$_POST['edbs_meeting_date'] = '2024-03-15';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '2024-03-15', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A date that doesn't parse as real Y-m-d (e.g. month 13) is
	 * rejected rather than saved.
	 */
	public function test_invalid_date_is_not_saved(): void {
		$_POST['edbs_meeting_date'] = '2024-13-45';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A date string that isn't in Y-m-d format at all is rejected, not
	 * reformatted or partially accepted.
	 */
	public function test_wrong_format_date_is_not_saved(): void {
		$_POST['edbs_meeting_date'] = '03/15/2024';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * The agenda URL is run through esc_url_raw() before saving.
	 */
	public function test_agenda_url_is_sanitized(): void {
		$input                            = 'https://example.com/agenda.pdf"><script>alert(1)</script>';
		$_POST['edbs_agenda_url'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		$saved = get_post_meta( $this->post_id, 'edbs_agenda_url', true );
		$this->assertSame( esc_url_raw( $input ), $saved );
	}

	/**
	 * The minutes URL is run through esc_url_raw() before saving.
	 */
	public function test_minutes_url_is_sanitized(): void {
		$input                             = 'javascript:alert(1)';
		$_POST['edbs_minutes_url'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		$saved = get_post_meta( $this->post_id, 'edbs_minutes_url', true );
		$this->assertSame( esc_url_raw( $input ), $saved );
	}

	/**
	 * The "not held" checkbox saves '1' when present in $_POST.
	 */
	public function test_not_held_checkbox_checked_saves_1(): void {
		$_POST['edbs_meeting_not_held'] = '1';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '1', get_post_meta( $this->post_id, 'edbs_meeting_not_held', true ) );
	}

	/**
	 * The "not held" checkbox saves an empty string when absent from
	 * $_POST, since unchecked checkboxes aren't submitted at all.
	 */
	public function test_not_held_checkbox_unchecked_saves_empty_string(): void {
		// Deliberately not setting edbs_meeting_not_held in $_POST.
		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_not_held', true ) );
	}

	/**
	 * The edbs_save_meeting_meta action fires after a successful save,
	 * so Pro plugin can save its own additional meta in the same request.
	 */
	public function test_save_meeting_meta_action_fires_on_success(): void {
		$fired_with_post_id = null;
		$callback            = static function ( int $post_id ) use ( &$fired_with_post_id ): void {
			$fired_with_post_id = $post_id;
		};
		add_action( 'edbs_save_meeting_meta', $callback );

		$this->meta_box->save_meta( $this->post_id );

		remove_action( 'edbs_save_meeting_meta', $callback );

		$this->assertSame( $this->post_id, $fired_with_post_id );
	}

	/**
	 * The action does not fire when the nonce is missing/invalid, since
	 * the method returns early before reaching it.
	 */
	public function test_save_meeting_meta_action_does_not_fire_without_valid_nonce(): void {
		$_POST = []; // No nonce at all.

		$fired    = false;
		$callback = static function () use ( &$fired ): void {
			$fired = true;
		};
		add_action( 'edbs_save_meeting_meta', $callback );

		$this->meta_box->save_meta( $this->post_id );

		remove_action( 'edbs_save_meeting_meta', $callback );

		$this->assertFalse( $fired );
	}
}
