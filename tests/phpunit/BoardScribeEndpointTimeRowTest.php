<?php
/**
 * Tests for the optional `time` key on BoardScribeEndpoint::build_meeting_row()
 * rows, and the time_format route argument.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers when a row carries a time, what it looks like, and what must not
 * change when it does.
 */
class BoardScribeEndpointTimeRowTest extends TestCase {

	/**
	 * Format args matching the REST route's shape.
	 *
	 * @var array<string, string>
	 */
	private array $format_args = [
		'held_date_format'     => 'Y/m/d',
		'not_held_date_format' => 'Y/m',
		'agenda_link_label'    => 'View Agenda',
		'minutes_link_label'   => 'View Minutes',
	];

	/**
	 * Creates a meeting with the given meta.
	 *
	 * @param array $meta Meta key/value pairs.
	 * @return int The post ID.
	 */
	private function create_meeting( array $meta = [] ): int {
		$post_id = self::factory()->post->create(
			[
				'post_type'  => 'edbs_meeting',
				'post_title' => 'Regular Meeting',
			]
		);

		$meta = array_merge( [ 'edbs_meeting_date' => '2026-03-12' ], $meta );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Builds the row for a meeting.
	 *
	 * @param int   $post_id     Meeting ID.
	 * @param array $format_args Extra format args.
	 * @return array
	 */
	private function row( int $post_id, array $format_args = [] ): array {
		return ( new BoardScribeEndpoint() )->build_meeting_row( $post_id, array_merge( $this->format_args, $format_args ) );
	}

	/**
	 * A meeting with a start time carries one <time> element.
	 */
	public function test_start_time_only(): void {
		$row = $this->row( $this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] ) );

		$this->assertSame( '<time datetime="17:00">5:00 pm</time>', $row['time'] );
	}

	/**
	 * A start and end time read as a range.
	 */
	public function test_start_and_end_time(): void {
		$row = $this->row(
			$this->create_meeting(
				[
					'edbs_meeting_start_time' => '17:00',
					'edbs_meeting_end_time'   => '19:30',
				]
			)
		);

		$this->assertSame( '<time datetime="17:00">5:00 pm</time> to <time datetime="19:30">7:30 pm</time>', $row['time'] );
	}

	/**
	 * An end time without a start time is still shown.
	 */
	public function test_end_time_only(): void {
		$row = $this->row( $this->create_meeting( [ 'edbs_meeting_end_time' => '19:00' ] ) );

		$this->assertSame( 'Ends <time datetime="19:00">7:00 pm</time>', $row['time'] );
	}

	/**
	 * The time_format arg formats the time; an empty one falls back to the default.
	 */
	public function test_time_format_arg(): void {
		$id = $this->create_meeting( [ 'edbs_meeting_start_time' => '17:05' ] );

		$this->assertSame( '<time datetime="17:05">17:05</time>', $this->row( $id, [ 'time_format' => 'H:i' ] )['time'] );
		$this->assertSame( '<time datetime="17:05">5:05 pm</time>', $this->row( $id, [ 'time_format' => '' ] )['time'] );
	}

	/**
	 * The date string and the agenda/minutes link labels never include the time.
	 */
	public function test_date_and_link_labels_are_unchanged_by_a_time(): void {
		$meta  = [
			'edbs_agenda_url'  => 'https://example.com/a.pdf',
			'edbs_minutes_url' => 'https://example.com/m.pdf',
		];
		$plain = $this->row( $this->create_meeting( $meta ) );
		$timed = $this->row(
			$this->create_meeting(
				array_merge(
					$meta,
					[
						'edbs_meeting_start_time' => '17:00',
						'edbs_meeting_end_time'   => '19:00',
					]
				)
			)
		);

		$this->assertArrayNotHasKey( 'time', $plain );
		$this->assertArrayHasKey( 'time', $timed );
		unset( $timed['time'] );
		$this->assertSame( $plain, $timed );
	}

	/**
	 * A meeting with no time, or only invalid stored values, has no time key.
	 */
	public function test_no_time_key_without_a_valid_time(): void {
		$this->assertArrayNotHasKey( 'time', $this->row( $this->create_meeting() ) );

		// Legacy or direct-database junk: written around the sanitizers.
		global $wpdb;
		$id = $this->create_meeting();
		$wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $id,
				'meta_key'   => 'edbs_meeting_start_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'soon', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		$this->assertArrayNotHasKey( 'time', $this->row( $id ) );
	}

	/**
	 * A not-held meeting has no time, however it was set.
	 */
	public function test_not_held_meeting_has_no_time(): void {
		$row = $this->row(
			$this->create_meeting(
				[
					'edbs_meeting_not_held'   => '1',
					'edbs_meeting_start_time' => '17:00',
				]
			)
		);

		$this->assertArrayNotHasKey( 'time', $row );
	}

	/**
	 * A missing or unparseable date leaves the "not available" placeholder
	 * alone, with no time beside it.
	 */
	public function test_undated_meeting_has_no_time(): void {
		foreach ( [ 'garbage', '' ] as $date ) {
			$row = $this->row(
				$this->create_meeting(
					[
						'edbs_meeting_date'       => $date,
						'edbs_meeting_start_time' => '17:00',
					]
				)
			);

			$this->assertArrayNotHasKey( 'time', $row, "date '{$date}'" );
		}
	}

	/**
	 * When a filter replaces the date (Pro's Date display override), the time
	 * is not added to wording the author chose in full.
	 */
	public function test_replaced_date_has_no_time(): void {
		$id     = $this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );
		$filter = static fn() => 'TBD';
		add_filter( 'edbs_meeting_formatted_date', $filter );

		$row = $this->row( $id );

		remove_filter( 'edbs_meeting_formatted_date', $filter );

		$this->assertSame( 'TBD', $row['date'] );
		$this->assertArrayNotHasKey( 'time', $row );
	}

	/**
	 * A filter that returns the date unchanged does not suppress the time.
	 */
	public function test_unchanged_date_filter_keeps_the_time(): void {
		$id     = $this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );
		$filter = static fn( $date ) => $date;
		add_filter( 'edbs_meeting_formatted_date', $filter );

		$row = $this->row( $id );

		remove_filter( 'edbs_meeting_formatted_date', $filter );

		$this->assertArrayHasKey( 'time', $row );
	}

	/**
	 * The time markup filter can change what is shown, and is sanitized after.
	 */
	public function test_time_filter_output_is_sanitized(): void {
		$id     = $this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );
		$filter = static fn() => '<strong>Evening</strong><script>alert(1)</script>';
		add_filter( 'edbs_meeting_formatted_time', $filter );

		$row = $this->row( $id );

		remove_filter( 'edbs_meeting_formatted_time', $filter );

		$this->assertSame( '<strong>Evening</strong>alert(1)', $row['time'] );
	}

	/**
	 * The time filter can drop the time entirely.
	 */
	public function test_time_filter_can_remove_the_time(): void {
		$id = $this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );
		add_filter( 'edbs_meeting_formatted_time', '__return_empty_string' );

		$row = $this->row( $id );

		remove_filter( 'edbs_meeting_formatted_time', '__return_empty_string' );

		$this->assertArrayNotHasKey( 'time', $row );
	}

	/**
	 * The edbs_meeting_row_data filter sees the time key.
	 */
	public function test_row_data_filter_receives_the_time(): void {
		$id       = $this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );
		$seen     = null;
		$callback = static function ( array $row ) use ( &$seen ): array {
			$seen = $row['time'] ?? null;
			return $row;
		};
		add_filter( 'edbs_meeting_row_data', $callback );

		$this->row( $id );

		remove_filter( 'edbs_meeting_row_data', $callback );

		$this->assertSame( '<time datetime="17:00">5:00 pm</time>', $seen );
	}

	/**
	 * The time_format route arg accepts a valid format and rejects one with a
	 * date or timezone character, and omitting it works (older callers).
	 */
	public function test_route_time_format_arg(): void {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );

		$request = new \WP_REST_Request( 'GET', '/edbs/v1/boardscribe' );
		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );

		$request = new \WP_REST_Request( 'GET', '/edbs/v1/boardscribe' );
		$request->set_param( 'time_format', 'H:i' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<time datetime="17:00">17:00</time>', $response->get_data()['meetings'][0]['time'] );

		$request = new \WP_REST_Request( 'GET', '/edbs/v1/boardscribe' );
		$request->set_param( 'time_format', 'g:i a T' );
		$this->assertSame( 400, rest_get_server()->dispatch( $request )->get_status() );
	}
	/**
	 * An empty time_format param is accepted and means "use the default". The
	 * front-end request builder sends '' for a config key an instance doesn't
	 * have (e.g. a cached page whose shortcode HTML predates the setting), and
	 * WordPress validates a REST arg before sanitizing it, so rejecting '' would
	 * stop every list on the site loading. The date format args still reject it,
	 * as they always have.
	 */
	public function test_route_accepts_an_empty_time_format(): void {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$this->create_meeting();
		$this->create_meeting( [ 'edbs_meeting_start_time' => '17:00' ] );

		$request = new \WP_REST_Request( 'GET', '/edbs/v1/boardscribe' );
		$request->set_param( 'time_format', '' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$meetings = $response->get_data()['meetings'];
		$this->assertCount( 2, $meetings );
		$this->assertSame( '<time datetime="17:00">5:00 pm</time>', $meetings[0]['time'] ?? $meetings[1]['time'] );
		$this->assertCount( 1, array_filter( $meetings, static fn( $row ) => isset( $row['time'] ) ), 'Only the timed meeting has a time key.' );
	}

	/**
	 * The posts_clauses callback is untyped: a core filter fired with other
	 * arguments must pass straight through, not throw.
	 */
	public function test_tie_break_callback_ignores_foreign_arguments(): void {
		$endpoint = new BoardScribeEndpoint();

		$this->assertSame( 'x', $endpoint->add_start_time_tie_break( 'x' ) );
		$this->assertSame( [ 'orderby' => 'a' ], $endpoint->add_start_time_tie_break( [ 'orderby' => 'a' ], null ) );
		$this->assertSame( [ 'orderby' => 'a' ], $endpoint->add_start_time_tie_break( [ 'orderby' => 'a' ], new \WP_Query() ) );
	}
}
