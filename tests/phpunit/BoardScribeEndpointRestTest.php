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
		$this->assertArrayHasKey( 'per_page', $data );
		$this->assertCount( 1, $data['meetings'] );
	}

	/**
	 * PRO-1395 #1: a password-protected meeting has no real public page for
	 * this endpoint to link to (meetings have no content of their own to
	 * gate), so the option protects nothing here - it's excluded from
	 * every list rather than appearing with live agenda/minutes links.
	 */
	public function test_password_protected_meetings_are_excluded(): void {
		$protected_id = $this->create_meeting( [ 'edbs_meeting_date' => '2024-03-15' ] );
		wp_update_post(
			[
				'ID'            => $protected_id,
				'post_password' => 'secret',
			]
		);
		$public_id = $this->create_meeting( [ 'edbs_meeting_date' => '2024-06-01' ] );

		$request  = new \WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 1, $data['total_entries'] );
		// Confirms it's specifically the public meeting that came back, not
		// just that the count happens to be right.
		$this->assertSame( get_the_title( $public_id ), $data['meetings'][0]['title'] );
	}

	/**
	 * PRO-1395 #3: per_page in the response is the effective count actually
	 * applied to the query - distinct from the raw posts_per_page param,
	 * which the endpoint caps (edbs_rest_max_per_page, default 100).
	 */
	public function test_per_page_reflects_the_capped_posts_per_page(): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'posts_per_page', 5000 );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 100, $data['per_page'] );
	}

	/**
	 * PRO-1419: "show all" is a feature - 0 and -1 (and input the route sanitizer
	 * maps to -1) must keep returning the bounded absolute max, not be clamped to
	 * the normal 100-item cap. Pinned so nobody "fixes" it into a regression.
	 *
	 * @dataProvider show_all_posts_per_page_values
	 *
	 * @param mixed $value The raw posts_per_page value sent to the route.
	 */
	public function test_show_all_values_resolve_to_the_absolute_max_not_the_normal_cap( $value ): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'posts_per_page', $value );

		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertSame( 500, $data['per_page'] );
	}

	/**
	 * Values that mean "show all" through the public route.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function show_all_posts_per_page_values(): array {
		return [
			'minus one'    => [ -1 ],
			'zero'         => [ 0 ],
			'negative'     => [ -5 ],
			'all'          => [ 'all' ],
			'non-numeric'  => [ 'abc' ],
			'empty string' => [ '' ],
		];
	}

	/**
	 * PRO-1419: the method itself treats a non-positive value like the route
	 * does (show all, bounded), so a direct call can't reach WP_Query with a raw 0.
	 */
	public function test_method_treats_non_positive_values_as_bounded_show_all(): void {
		$endpoint = new \EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint();

		foreach ( [ 0, -1, -5 ] as $value ) {
			$request = new \WP_REST_Request( 'GET', self::ROUTE );
			$request->set_param( 'posts_per_page', $value );

			$this->assertSame( 500, $endpoint->get_meetings( $request )->get_data()['per_page'], "posts_per_page={$value}" );
		}
	}

	/**
	 * PRO-1419: a positive value is still capped at the normal 100.
	 */
	public function test_positive_values_are_still_capped_at_the_normal_max(): void {
		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'posts_per_page', 99999 );

		$this->assertSame( 100, rest_get_server()->dispatch( $request )->get_data()['per_page'] );
	}

	/**
	 * PRO-1395 #2: meetings sharing the same edbs_meeting_date must still
	 * sort deterministically (newest ID first, as a tie-break) rather than
	 * in whatever order MySQL happens to return ties under LIMIT/OFFSET -
	 * otherwise one could appear on two pages and another on none.
	 */
	public function test_meetings_sharing_a_date_are_ordered_deterministically_by_id(): void {
		$first  = $this->create_meeting( [ 'edbs_meeting_date' => '2024-03-15' ] );
		$second = $this->create_meeting( [ 'edbs_meeting_date' => '2024-03-15' ] );
		$third  = $this->create_meeting( [ 'edbs_meeting_date' => '2024-03-15' ] );

		$request  = new \WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 3, $data['total_entries'] );
		$titles = wp_list_pluck( $data['meetings'], 'title' );
		$this->assertSame(
			[ get_the_title( $third ), get_the_title( $second ), get_the_title( $first ) ],
			$titles
		);
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
}
