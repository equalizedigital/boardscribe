<?php
/**
 * Tests for writing a meeting's start and end time through the core REST API.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use EqualizeDigital\BoardScribe\Helpers\MeetingTime;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * When the post type supports custom-fields (it does not by default), a time
 * that is not HH:MM is rejected with a 400 instead of being dropped with a
 * 200. On a stock install REST ignores meeting meta; the sanitize callback and
 * prune hook protect every other write path.
 */
class MeetingTimeRestMetaTest extends TestCase {

	/**
	 * Logs in as an administrator and boots the REST server.
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// Meetings do not expose meta over REST (the post type only supports a
		// title), so switch it on to check what the time keys accept if it is.
		add_post_type_support( 'edbs_meeting', 'custom-fields' );
		( new MetaBox() )->register_post_meta();
		$this->reset_rest_controller();
		rest_get_server();
	}

	/**
	 * Turns the support back off and drops the REST server built with it.
	 */
	public function tear_down(): void {
		remove_post_type_support( 'edbs_meeting', 'custom-fields' );
		$this->reset_rest_controller();

		parent::tear_down();
	}

	/**
	 * Drops the REST server and the post type's cached controller, whose schema
	 * is built once and would otherwise ignore the support change.
	 */
	private function reset_rest_controller(): void {
		$GLOBALS['wp_rest_server']                          = null;
		get_post_type_object( 'edbs_meeting' )->rest_controller = null;
	}

	/**
	 * Sends a draft meeting create request with the given time meta.
	 *
	 * @param array $meta Meta sent with the request.
	 * @return WP_REST_Response
	 */
	private function create( array $meta ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting' );
		$request->set_param( 'title', 'REST time meeting' );
		$request->set_param( 'status', 'draft' );
		$request->set_param( 'meta', $meta );

		return rest_do_request( $request );
	}

	/**
	 * Values that are not a time are rejected.
	 *
	 * @dataProvider invalid_times
	 *
	 * @param mixed  $value The value sent.
	 * @param string $key   The meta key it is sent under.
	 */
	public function test_invalid_time_is_rejected( $value, string $key ): void {
		$response = $this->create( [ $key => $value ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_pattern', $response->get_data()['code'] );
	}

	/**
	 * Invalid times, for both meta keys.
	 *
	 * @return array
	 */
	public function invalid_times(): array {
		return [
			'text, start'      => [ 'abc', MeetingTime::META_START ],
			'hour 24, start'   => [ '24:00', MeetingTime::META_START ],
			'minute 60, start' => [ '12:60', MeetingTime::META_START ],
			'unpadded, start'  => [ '9:00', MeetingTime::META_START ],
			'12 hour, start'   => [ '5:00 PM', MeetingTime::META_START ],
			'text, end'        => [ 'abc', MeetingTime::META_END ],
			'hour 25, end'     => [ '25:00', MeetingTime::META_END ],
		];
	}

	/**
	 * A valid time is stored, seconds are dropped, and an empty value stores nothing.
	 */
	public function test_valid_and_empty_times_are_accepted(): void {
		$response = $this->create(
			[
				MeetingTime::META_START => '17:00:30',
				MeetingTime::META_END   => '19:15',
			]
		);

		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];
		$this->assertSame( '17:00', get_post_meta( $id, MeetingTime::META_START, true ) );
		$this->assertSame( '19:15', get_post_meta( $id, MeetingTime::META_END, true ) );

		$cleared = $this->create(
			[
				MeetingTime::META_START => '',
				MeetingTime::META_END   => '',
			]
		);

		$this->assertSame( 201, $cleared->get_status() );
		$this->assertFalse( metadata_exists( 'post', $cleared->get_data()['id'], MeetingTime::META_START ) );
		$this->assertFalse( metadata_exists( 'post', $cleared->get_data()['id'], MeetingTime::META_END ) );
	}

	/**
	 * Updating a stored time to empty removes it, and sending null still deletes it.
	 */
	public function test_updating_to_empty_or_null_removes_the_time(): void {
		$id = $this->create( [ MeetingTime::META_START => '09:00' ] )->get_data()['id'];

		$update = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting/' . $id );
		$update->set_param( 'meta', [ MeetingTime::META_START => '' ] );
		$this->assertSame( 200, rest_do_request( $update )->get_status() );
		$this->assertFalse( metadata_exists( 'post', $id, MeetingTime::META_START ) );

		update_post_meta( $id, MeetingTime::META_START, '10:00' );
		$null = new WP_REST_Request( 'POST', '/wp/v2/edbs_meeting/' . $id );
		$null->set_param( 'meta', [ MeetingTime::META_START => null ] );
		$this->assertSame( 200, rest_do_request( $null )->get_status() );
		$this->assertFalse( metadata_exists( 'post', $id, MeetingTime::META_START ) );
	}
}
