<?php
/**
 * Tests for MeetingDateGuard.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MeetingDateGuard;
use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * A draft may lack a meeting date; a published or scheduled meeting may not.
 */
class MeetingDateGuardTest extends TestCase {

	/**
	 * The guard under test.
	 *
	 * @var MeetingDateGuard
	 */
	private MeetingDateGuard $guard;

	/**
	 * Creates the guard and an editor to act as.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->guard = new MeetingDateGuard();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
	}

	/**
	 * Resets the current user.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Creates a meeting with the given status and optional date.
	 *
	 * @param string $status Post status.
	 * @param string $date   Meeting date, or '' for none.
	 * @return int The post ID.
	 */
	private function meeting( string $status, string $date = '' ): int {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'edbs_meeting',
				'post_status' => $status,
			]
		);
		if ( '' !== $date ) {
			update_post_meta( $post_id, 'edbs_meeting_date', $date );
		}
		return $post_id;
	}

	/**
	 * Only real Y-m-d calendar dates are valid.
	 */
	public function test_is_valid_date(): void {
		$this->assertTrue( MeetingDateGuard::is_valid_date( '2024-03-15' ) );
		$this->assertFalse( MeetingDateGuard::is_valid_date( '' ) );
		$this->assertFalse( MeetingDateGuard::is_valid_date( 'garbage' ) );
		$this->assertFalse( MeetingDateGuard::is_valid_date( '2024-02-31' ) );
		$this->assertFalse( MeetingDateGuard::is_valid_date( null ) );
	}

	/**
	 * Meta writes store a valid date, and an empty string for anything else.
	 */
	public function test_meta_sanitize_stores_invalid_dates_as_empty(): void {
		$post_id = $this->meeting( 'draft' );

		update_post_meta( $post_id, 'edbs_meeting_date', '2024-03-15' );
		$this->assertSame( '2024-03-15', get_post_meta( $post_id, 'edbs_meeting_date', true ) );

		update_post_meta( $post_id, 'edbs_meeting_date', 'garbage' );
		$this->assertSame( '', get_post_meta( $post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A dateless published meeting is reverted to draft.
	 */
	public function test_dateless_publish_is_reverted_to_draft(): void {
		$post_id = $this->meeting( 'publish' );

		$this->guard->revert_dateless_publish( $post_id );

		$this->assertSame( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * A dateless scheduled meeting is reverted too.
	 */
	public function test_dateless_scheduled_meeting_is_reverted_to_draft(): void {
		$post_id = $this->meeting( 'future' );

		$this->guard->revert_dateless_publish( $post_id );

		$this->assertSame( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * A published meeting with a date, and a dateless draft, are left alone.
	 */
	public function test_dated_publish_and_dateless_draft_are_untouched(): void {
		$published = $this->meeting( 'publish', '2024-03-15' );
		$draft     = $this->meeting( 'draft' );

		$this->guard->revert_dateless_publish( $published );
		$this->guard->revert_dateless_publish( $draft );

		$this->assertSame( 'publish', get_post_status( $published ) );
		$this->assertSame( 'draft', get_post_status( $draft ) );
	}

	/**
	 * Publishing with an explicit empty or invalid date over REST is rejected.
	 *
	 * @dataProvider invalid_rest_dates
	 *
	 * @param mixed $date The date sent in the request's meta.
	 */
	public function test_rest_publish_with_invalid_date_is_rejected( $date ): void {
		$request = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting' );
		$request->set_param( 'status', 'publish' );
		$request->set_param( 'meta', [ 'edbs_meeting_date' => $date ] );

		$result = $this->guard->validate_rest_publish( new stdClass(), $request );

		$this->assertWPError( $result );
		$this->assertSame( 'edbs_meeting_date_required', $result->get_error_code() );
	}

	/**
	 * Invalid values for the REST test.
	 *
	 * @return array
	 */
	public function invalid_rest_dates(): array {
		return [
			'empty'   => [ '' ],
			'garbage' => [ 'garbage' ],
		];
	}

	/**
	 * A valid date, a draft, or a request without a date all pass through.
	 */
	public function test_rest_valid_cases_pass_through(): void {
		$with_date = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting' );
		$with_date->set_param( 'status', 'publish' );
		$with_date->set_param( 'meta', [ 'edbs_meeting_date' => '2024-03-15' ] );

		$draft = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting' );
		$draft->set_param( 'status', 'draft' );
		$draft->set_param( 'meta', [ 'edbs_meeting_date' => '' ] );

		$no_meta = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting' );
		$no_meta->set_param( 'status', 'publish' );

		foreach ( [ $with_date, $draft, $no_meta ] as $request ) {
			$this->assertNotWPError( $this->guard->validate_rest_publish( new stdClass(), $request ) );
		}
	}

	/**
	 * Through the real meta box save: a published meeting submitted with no
	 * date ends up a draft, and with a date stays published.
	 */
	public function test_meta_box_save_reverts_only_a_dateless_publish(): void {
		$this->guard->register();
		$meta_box = new MetaBox();

		$dateless = $this->meeting( 'publish' );
		$dated    = $this->meeting( 'publish' );

		$_POST = [
			'edbs_meeting_meta_nonce' => wp_create_nonce( 'edbs_save_meeting_meta' ),
			'edbs_meta_box_rendered'  => '1',
		];
		$meta_box->save_meta( $dateless );

		$_POST['edbs_meeting_date'] = '2024-03-15';
		$meta_box->save_meta( $dated );
		$_POST = [];

		$this->assertSame( 'draft', get_post_status( $dateless ) );
		$this->assertSame( 'publish', get_post_status( $dated ) );
	}
}
