<?php
/**
 * Tests for MeetingSort field registration and REST order application.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\MeetingSort;
use WP_REST_Request;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * add_field() is a pure array transform hooked onto the free plugin's
 * edbs_shortcode_field_registry filter; apply_order() transforms the
 * WP_Query args from a REST request param. Both are tested directly,
 * since the free plugin isn't loaded in this test environment.
 */
class MeetingSortTest extends TestCase {

	/**
	 * Adds exactly one "order" descriptor with asc/desc choices.
	 */
	public function test_adds_the_order_field(): void {
		$fields = ( new MeetingSort() )->add_field( [] );
		$order  = end( $fields );

		$this->assertCount( 1, $fields );
		$this->assertSame( 'order', $order['key'] );
		$this->assertSame( 'select', $order['type'] );
		$this->assertSame( 'general', $order['group'] );
		$this->assertSame( 'desc', $order['default'] );
		$this->assertTrue( $order['rest_arg'] );
		$this->assertArrayHasKey( 'asc', $order['choices'] );
		$this->assertArrayHasKey( 'desc', $order['choices'] );
	}

	/**
	 * Existing registry entries are preserved, not replaced.
	 */
	public function test_preserves_existing_fields(): void {
		$existing = [ [ 'key' => 'included_years', 'type' => 'text' ] ];
		$fields   = ( new MeetingSort() )->add_field( $existing );

		$this->assertSame( 'included_years', $fields[0]['key'] );
		$this->assertCount( 2, $fields );
	}

	/**
	 * order=asc flips the query order to ASC.
	 */
	public function test_applies_asc_order(): void {
		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'order', 'asc' );

		$args = ( new MeetingSort() )->apply_order( [ 'order' => 'DESC' ], $request );

		$this->assertSame( 'ASC', $args['order'] );
	}

	/**
	 * order=desc keeps the query order at DESC (explicit or default).
	 */
	public function test_applies_desc_order(): void {
		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'order', 'desc' );

		$args = ( new MeetingSort() )->apply_order( [ 'order' => 'DESC' ], $request );

		$this->assertSame( 'DESC', $args['order'] );
	}

	/**
	 * A missing or unrecognized order param leaves the args untouched so
	 * the endpoint keeps its hardcoded newest-first default.
	 */
	public function test_ignores_unknown_or_missing_order(): void {
		$original = [ 'order' => 'DESC', 'meta_key' => 'edbs_meeting_date' ];

		$request  = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$no_param = ( new MeetingSort() )->apply_order( $original, $request );
		$this->assertSame( $original, $no_param );

		$junk_request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$junk_request->set_param( 'order', 'random' );
		$junk = ( new MeetingSort() )->apply_order( $original, $junk_request );
		$this->assertSame( $original, $junk );
	}

	/**
	 * register() wires the field onto edbs_shortcode_field_registry and
	 * the order filter onto edbs_rest_query_args, so the free plugin's
	 * machinery picks both up. Filters are removed again afterwards to
	 * avoid leaking the callbacks into later tests.
	 */
	public function test_register_wires_both_hooks(): void {
		$component = new MeetingSort();
		$component->register();

		$fields = apply_filters( 'edbs_shortcode_field_registry', [] );
		$keys   = wp_list_pluck( $fields, 'key' );
		$this->assertContains( 'order', $keys );

		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'order', 'asc' );
		$result = apply_filters( 'edbs_rest_query_args', [ 'order' => 'DESC' ], $request );
		$this->assertSame( 'ASC', $result['order'] );

		remove_filter( 'edbs_shortcode_field_registry', [ $component, 'add_field' ] );
		remove_filter( 'edbs_rest_query_args', [ $component, 'apply_order' ] );
	}
}
