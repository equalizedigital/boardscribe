<?php
/**
 * Integration tests for the registered /edbs/v1/boardscribe/ REST route.
 *
 * @package EqualizeDigital\BoardScribe
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Dispatches real requests through the REST server, rather than calling
 * BoardScribeEndpoint's methods directly - this is the only coverage
 * that proves register_route() is actually wired correctly (route path,
 * args schema, validate_callback) rather than just proving the
 * underlying functions work in isolation.
 */
class BoardScribeEndpointRestTest extends TestCase {

	const ROUTE = '/edbs/v1/boardscribe';

	/**
	 * Ensures the REST server (and therefore rest_api_init/register_route)
	 * has run before each test.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Creates a meeting post with the given meta.
	 *
	 * @param array $meta Meta key/value pairs to set on the post.
	 * @return int The created post ID.
	 */
	private function create_meeting( array $meta = [] ): int {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * A plain request against the real route returns 200 with the
	 * expected top-level response shape.
	 */
	public function test_route_returns_expected_shape(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-03-15' ] );

		$request  = new \WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'meetings', $data );
		$this->assertArrayHasKey( 'max_num_pages', $data );
		$this->assertArrayHasKey( 'current_page', $data );
		$this->assertArrayHasKey( 'total_entries', $data );
		$this->assertCount( 1, $data['meetings'] );
	}

	/**
	 * An invalid held_date_format is rejected with 400 by the route's
	 * own validate_callback, end to end - not just by calling
	 * validate_date_format() directly.
	 */
	public function test_invalid_held_date_format_is_rejected_with_400(): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'held_date_format', '<script>' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * An invalid not_held_date_format is rejected with 400 too.
	 */
	public function test_invalid_not_held_date_format_is_rejected_with_400(): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'not_held_date_format', '\\<\\s\\c\\r\\i\\p\\t\\>' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * posts_per_page is honored against real query results.
	 */
	public function test_posts_per_page_limits_results(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-01-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-06-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-12-01' ] );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'posts_per_page', 2 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data['meetings'] );
		$this->assertSame( 3, $data['total_entries'] );
		// assertEquals, not assertSame: WP_Query::$max_num_pages is
		// ceil()'d, which returns float in PHP - whether that surfaces as
		// int or float here varies by WP core version (confirmed via CI:
		// WP 6.2 returns float(2.0), WP latest returns int(2)). The
		// number of pages being 2 is the contract; the PHP-level type
		// isn't.
		$this->assertEquals( 2, $data['max_num_pages'] );
	}

	/**
	 * included_years actually filters results against real meta_query
	 * construction, not just the sanitized param value.
	 */
	public function test_included_years_filters_results(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2023-05-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-05-01' ] );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'included_years', '2024' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $data['total_entries'] );
	}

	/**
	 * Posts without a meeting date meta value are excluded from results,
	 * per the meta_query's EXISTS clause.
	 */
	public function test_posts_without_meeting_date_are_excluded(): void {
		$this->create_meeting(); // No edbs_meeting_date meta at all.

		$request  = new \WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 0, $data['total_entries'] );
	}

	/**
	 * start_date and end_date together filter to an arbitrary range that
	 * spans a calendar year boundary — the fiscal-year use case
	 * included_years can't express (see boardscribe#48).
	 */
	public function test_start_and_end_date_filter_a_fiscal_year_range(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2025-06-30' ] ); // Just before the range.
		$this->create_meeting( [ 'edbs_meeting_date' => '2025-07-01' ] ); // Range start, inclusive.
		$this->create_meeting( [ 'edbs_meeting_date' => '2026-01-15' ] ); // Inside the range.
		$this->create_meeting( [ 'edbs_meeting_date' => '2026-06-30' ] ); // Range end, inclusive.
		$this->create_meeting( [ 'edbs_meeting_date' => '2026-07-01' ] ); // Just after the range.

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'start_date', '2025-07-01' );
		$request->set_param( 'end_date', '2026-06-30' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 3, $data['total_entries'] );
	}

	/**
	 * start_date alone is an open-ended "on or after" filter.
	 */
	public function test_start_date_alone_filters_open_ended(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-01-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2025-01-01' ] );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'start_date', '2024-06-01' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $data['total_entries'] );
	}

	/**
	 * end_date alone is an open-ended "on or before" filter.
	 */
	public function test_end_date_alone_filters_open_ended(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-01-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2025-01-01' ] );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'end_date', '2024-06-01' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $data['total_entries'] );
	}

	/**
	 * A start_date/end_date range takes priority over included_years
	 * entirely when both are sent — not combined/intersected with it —
	 * since a caller sending both almost certainly means the explicit
	 * range.
	 */
	public function test_start_date_takes_priority_over_included_years(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2023-05-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-05-01' ] );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'included_years', '2024' );
		$request->set_param( 'start_date', '2023-01-01' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Both meetings fall on/after 2023-01-01, so start_date alone
		// (ignoring included_years=2024) returns both.
		$this->assertSame( 2, $data['total_entries'] );
	}

	/**
	 * An invalid (non-existent) start_date is rejected with 400 by the
	 * route's own validate_callback, end to end.
	 */
	public function test_invalid_start_date_is_rejected_with_400(): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'start_date', '2024-02-30' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * An invalid end_date is rejected with 400 too.
	 */
	public function test_invalid_end_date_is_rejected_with_400(): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'end_date', 'not-a-date' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * available_years is omitted unless requested.
	 */
	public function test_available_years_omitted_by_default(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-05-01' ] );

		$request  = new \WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'available_years', $data );
	}

	/**
	 * include_available_years=1 returns every distinct year with a
	 * meeting, newest first, ignoring included_years on the same request.
	 */
	public function test_include_available_years_returns_distinct_years_unfiltered(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2023-05-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-05-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-11-01' ] ); // Same year as above — must not duplicate.
		$this->create_meeting( [ 'edbs_meeting_date' => '2026-01-15' ] );
		$this->create_meeting(); // No date meta — must not surface as a year.

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'include_available_years', '1' );
		$request->set_param( 'included_years', '2024' ); // Scopes meetings, not available_years.

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'available_years', $data );
		$this->assertSame( [ 2026, 2024, 2023 ], $data['available_years'] );
		// The meetings list itself is still scoped by included_years.
		$this->assertSame( 2, $data['total_entries'] );
	}

	/**
	 * available_years is scoped by edbs_rest_query_args too, so a Pro
	 * callback excluding posts from the main query excludes them here.
	 */
	public function test_available_years_is_scoped_by_edbs_rest_query_args(): void {
		$excluded_id = $this->create_meeting( [ 'edbs_meeting_date' => '2025-05-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-05-01' ] );

		// A type-hinted $request param (matching the filter's documented
		// signature) proves get_available_years() passes the real
		// WP_REST_Request through, not null - a callback declared this
		// way would fatal on a null argument.
		$received_request = null;
		$callback          = static function ( array $args, \WP_REST_Request $request ) use ( $excluded_id, &$received_request ): array {
			$received_request      = $request;
			$args['post__not_in'] = [ $excluded_id ];
			return $args;
		};
		add_filter( 'edbs_rest_query_args', $callback, 10, 2 );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'include_available_years', '1' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'edbs_rest_query_args', $callback );

		$this->assertSame( [ 2024 ], $data['available_years'] );
		$this->assertSame( $request, $received_request );
	}

	/**
	 * available_years parses legacy d/m/Y and m/d/Y dates correctly,
	 * unlike a raw SQL YEAR() on those formats.
	 */
	public function test_available_years_parses_legacy_slash_formatted_dates(): void {
		// m/d/Y with day > 12 isn't covered here: parse_date() tries d/m/Y
		// first, and DateTime's lenient overflow "succeeds" on it with the
		// wrong date instead of falling through - a pre-existing bug in
		// parse_date() itself, unrelated to this endpoint.
		$this->create_meeting( [ 'edbs_meeting_date' => '15/03/2023' ] ); // d/m/Y

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'include_available_years', '1' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( [ 2023 ], $data['available_years'] );
	}

	/**
	 * available_years is bounded by edbs_rest_absolute_max_per_page, the
	 * same cap get_meetings() applies to -1/"show all" requests — this is
	 * an anonymous route, so its query can't be allowed to run unbounded.
	 */
	public function test_available_years_is_bounded_by_absolute_max_per_page(): void {
		$this->create_meeting( [ 'edbs_meeting_date' => '2026-01-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2025-01-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2023-01-01' ] );

		$callback = static function (): int {
			return 2;
		};
		add_filter( 'edbs_rest_absolute_max_per_page', $callback );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'include_available_years', '1' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'edbs_rest_absolute_max_per_page', $callback );

		// Capped to the 2 most recent meetings (newest-first order), so
		// the oldest year (2023) is excluded rather than every year
		// being scanned regardless of cost.
		$this->assertSame( [ 2026, 2025 ], $data['available_years'] );
	}

	/**
	 * available_years uses suppress_filters => false, matching
	 * get_meetings()'s own WP_Query - a posts_where/posts_join clause a
	 * visibility-scoping plugin adds to the main query must apply here
	 * too, or the switcher could reveal the existence of a year whose
	 * only meetings are meant to be hidden.
	 */
	public function test_available_years_honors_posts_where_clause_filters(): void {
		$hidden_id = $this->create_meeting( [ 'edbs_meeting_date' => '2025-05-01' ] );
		$this->create_meeting( [ 'edbs_meeting_date' => '2024-05-01' ] );

		$callback = static function ( string $where, \WP_Query $query ) use ( $hidden_id ): string {
			global $wpdb;
			if ( 'edbs_meeting' === $query->get( 'post_type' ) ) {
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID != %d", $hidden_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->posts is a core table name, not user input.
			}
			return $where;
		};
		add_filter( 'posts_where', $callback, 10, 2 );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'include_available_years', '1' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'posts_where', $callback );

		$this->assertSame( [ 2024 ], $data['available_years'] );
	}
}
