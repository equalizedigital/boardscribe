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
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meetings' ] );

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
}
