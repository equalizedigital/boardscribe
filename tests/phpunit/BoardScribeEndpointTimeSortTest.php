<?php
/**
 * Tests for same-day ordering by start time on the boardscribe REST route.
 *
 * @package EqualizeDigital\BoardScribe
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Meetings still order by date first; a start time only breaks a same-day
 * tie, and a site where no meeting has a time keeps its existing order.
 */
class BoardScribeEndpointTimeSortTest extends TestCase {

	const ROUTE = '/edbs/v1/boardscribe';

	/**
	 * Boots a fresh REST server so the route is registered.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Creates a meeting and returns its ID.
	 *
	 * @param string $date  Y-m-d meeting date.
	 * @param string $start HH:MM start time, or '' for none.
	 * @return int
	 */
	private function meeting( string $date, string $start = '' ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		wp_update_post(
			[
				'ID'         => $id,
				'post_title' => trim( $date . ' ' . $start ) . ' #' . $id,
			]
		);
		update_post_meta( $id, 'edbs_meeting_date', $date );

		if ( '' !== $start ) {
			update_post_meta( $id, 'edbs_meeting_start_time', $start );
		}

		return $id;
	}

	/**
	 * Returns the titles the route lists, in order.
	 *
	 * @param array $params Request params.
	 * @return string[]
	 */
	private function titles( array $params = [] ): array {
		return array_map( [ $this, 'strip_id' ], $this->raw_titles( $params ) );
	}

	/**
	 * Returns the meeting IDs the route lists, in order.
	 *
	 * @param array $params Request params.
	 * @return int[]
	 */
	private function ids( array $params = [] ): array {
		return array_map(
			static function ( string $title ): int {
				preg_match( '/#(\d+)$/', $title, $matches );
				return (int) $matches[1];
			},
			$this->raw_titles( $params )
		);
	}

	/**
	 * Returns the titles the route lists, in order, with their ID suffix.
	 *
	 * @param array $params Request params.
	 * @return string[]
	 */
	private function raw_titles( array $params ): array {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		return wp_list_pluck( $response->get_data()['meetings'], 'title' );
	}

	/**
	 * Drops the unique " #ID" suffix a meeting's title carries.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private function strip_id( string $title ): string {
		return preg_replace( '/ #\d+$/', '', $title );
	}

	/**
	 * Returns a meeting's title without its ID suffix.
	 *
	 * @param int $id Meeting ID.
	 * @return string
	 */
	private function label( int $id ): string {
		return $this->strip_id( get_the_title( $id ) );
	}

	/**
	 * Same-day meetings list the later start first (newest-first, like the date).
	 */
	public function test_same_day_meetings_order_by_start_time_descending(): void {
		$this->meeting( '2026-03-12', '09:00' );
		$this->meeting( '2026-03-12', '18:00' );
		$this->meeting( '2026-03-12', '13:30' );

		$this->assertSame(
			[ '2026-03-12 18:00', '2026-03-12 13:30', '2026-03-12 09:00' ],
			$this->titles()
		);
	}

	/**
	 * order=asc reverses the time tie-break along with the date.
	 */
	public function test_ascending_order_reverses_the_time_tie_break(): void {
		$this->meeting( '2026-03-12', '18:00' );
		$this->meeting( '2026-03-12', '09:00' );
		$this->meeting( '2026-03-11', '23:00' );

		$this->assertSame(
			[ '2026-03-11 23:00', '2026-03-12 09:00', '2026-03-12 18:00' ],
			$this->titles( [ 'order' => 'asc' ] )
		);
	}

	/**
	 * The date always wins: a late time on an earlier day never jumps ahead.
	 */
	public function test_date_still_orders_before_time(): void {
		$this->meeting( '2026-03-11', '23:59' );
		$this->meeting( '2026-03-12', '00:01' );

		$this->assertSame( [ '2026-03-12 00:01', '2026-03-11 23:59' ], $this->titles() );
	}

	/**
	 * A meeting with no time is never dropped, and sits after the timed ones
	 * on its day when newest-first (before them when oldest-first).
	 */
	public function test_untimed_meetings_stay_in_the_results(): void {
		$timed   = $this->meeting( '2026-03-12', '10:00' );
		$untimed = $this->meeting( '2026-03-12' );

		$this->assertSame(
			[ $this->label( $timed ), $this->label( $untimed ) ],
			$this->titles()
		);
		$this->assertSame(
			[ $this->label( $untimed ), $this->label( $timed ) ],
			$this->titles( [ 'order' => 'asc' ] )
		);
	}

	/**
	 * With no times anywhere, order is date then ID, newest first: exactly
	 * what it was before times existed.
	 */
	public function test_site_without_times_keeps_its_order(): void {
		$a = $this->meeting( '2026-03-12' );
		$b = $this->meeting( '2026-03-12' );
		$c = $this->meeting( '2026-01-05' );
		$d = $this->meeting( '2026-03-12' );

		$this->assertSame( [ $d, $b, $a, $c ], $this->ids() );
		$this->assertSame( [ $c, $a, $b, $d ], $this->ids( [ 'order' => 'asc' ] ) );
	}

	/**
	 * Other meta on a meeting (agenda links, notes) never changes the order of
	 * untimed meetings. A meta_query based tie-break ordered by an arbitrary one
	 * of a post's meta values; the dedicated start-time join must not.
	 */
	public function test_other_meta_does_not_affect_untimed_order(): void {
		$a = $this->meeting( '2026-03-12' );
		$b = $this->meeting( '2026-03-12' );
		$c = $this->meeting( '2026-03-12' );

		update_post_meta( $a, 'edbs_agenda_url', 'https://example.com/zzz.pdf' );
		update_post_meta( $a, 'edbs_minutes_url', 'https://example.com/zzz-minutes.pdf' );
		update_post_meta( $c, 'edbs_agenda_url', 'https://example.com/aaa.pdf' );
		update_post_meta( $b, 'unrelated_meta', '9999' );

		$this->assertSame( [ $c, $b, $a ], $this->ids() );
		$this->assertSame( [ $a, $b, $c ], $this->ids( [ 'order' => 'asc' ] ) );
	}

	/**
	 * Paging through same-day ties neither repeats nor loses a meeting.
	 */
	public function test_pagination_is_stable_across_same_day_ties(): void {
		$created = [];
		foreach ( [ '08:00', '', '12:00', '', '17:00', '' ] as $start ) {
			$created[] = $this->meeting( '2026-03-12', $start );
		}

		$seen = [];
		foreach ( [ 1, 2, 3 ] as $page ) {
			$seen = array_merge( $seen, $this->ids( [ 'posts_per_page' => 2, 'page' => $page ] ) );
		}

		sort( $created );
		$sorted = $seen;
		sort( $sorted );

		$this->assertCount( 6, $seen );
		$this->assertSame( $created, $sorted, 'Every meeting appears exactly once across the pages.' );
		$this->assertSame( $seen, $this->ids( [ 'posts_per_page' => 6 ] ), 'Paged order equals the single-page order.' );
	}

	/**
	 * A meeting that somehow has several start-time rows (add_post_meta() with
	 * $unique = false) is still listed once and paging is unaffected, because
	 * WP_Query groups the meta-queried results by post ID.
	 */
	public function test_a_meeting_with_duplicate_start_time_rows_is_listed_once(): void {
		$early     = $this->meeting( '2026-03-12', '09:00' );
		$duplicate = $this->meeting( '2026-03-12', '13:00' );
		add_post_meta( $duplicate, 'edbs_meeting_start_time', '13:00', false );
		add_post_meta( $duplicate, 'edbs_meeting_start_time', '08:00', false );
		$untimed = $this->meeting( '2026-03-12' );

		$this->assertCount( 3, get_post_meta( $duplicate, 'edbs_meeting_start_time', false ) );

		foreach ( [ 'desc', 'asc' ] as $order ) {
			$all = $this->ids( [ 'order' => $order ] );
			$this->assertEqualsCanonicalizing( [ $early, $duplicate, $untimed ], $all, "Every meeting is listed exactly once ($order)." );

			$paged = array_merge(
				$this->ids( [ 'order' => $order, 'posts_per_page' => 2, 'page' => 1 ] ),
				$this->ids( [ 'order' => $order, 'posts_per_page' => 2, 'page' => 2 ] )
			);
			$this->assertSame( $all, $paged, "Paged order equals the single-page order ($order)." );
		}

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'posts_per_page', 2 );
		$data = rest_do_request( $request )->get_data();

		$this->assertSame( 3, $data['total_entries'] );
		$this->assertEquals( 2, $data['max_num_pages'] );
	}

	/**
	 * Untimed meetings sort on the opposite side of timed ones by direction,
	 * because a missing start time is NULL: first when ascending, last when
	 * descending. Documented in the readme and the data model.
	 */
	public function test_untimed_meetings_sort_first_ascending_and_last_descending(): void {
		$timed   = $this->meeting( '2026-03-12', '09:00' );
		$untimed = $this->meeting( '2026-03-12' );

		$this->assertSame( [ $timed, $untimed ], $this->ids( [ 'order' => 'desc' ] ) );
		$this->assertSame( [ $untimed, $timed ], $this->ids( [ 'order' => 'asc' ] ) );
	}

	/**
	 * The date range and date scope filters still apply alongside the time clause.
	 */
	public function test_date_filters_still_apply(): void {
		$this->meeting( '2026-03-12', '09:00' );
		$this->meeting( '2026-04-12', '09:00' );
		$this->meeting( '2026-05-12' );

		$this->assertSame(
			[ '2026-04-12 09:00' ],
			$this->titles(
				[
					'start_date' => '2026-04-01',
					'end_date'   => '2026-04-30',
				]
			)
		);
	}
}
