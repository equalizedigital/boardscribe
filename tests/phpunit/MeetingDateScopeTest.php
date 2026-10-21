<?php
/**
 * Tests for MeetingDateScope field registration and REST scope filtering.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\MeetingDateScope;
use WP_REST_Request;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * add_field() is a pure array transform hooked onto the free plugin's
 * edbs_shortcode_field_registry filter; apply_date_scope() transforms the
 * WP_Query args from a REST request param. Both are tested directly,
 * since the free plugin isn't loaded in this test environment.
 */
class MeetingDateScopeTest extends TestCase {

	/**
	 * Adds exactly one "date_scope" descriptor with all/upcoming/past choices.
	 */
	public function test_adds_the_date_scope_field(): void {
		$fields = ( new MeetingDateScope() )->add_field( [] );
		$scope  = end( $fields );

		$this->assertCount( 1, $fields );
		$this->assertSame( 'date_scope', $scope['key'] );
		$this->assertSame( 'select', $scope['type'] );
		$this->assertSame( 'general', $scope['group'] );
		$this->assertSame( 'all', $scope['default'] );
		$this->assertTrue( $scope['rest_arg'] );
		$this->assertArrayHasKey( 'all', $scope['choices'] );
		$this->assertArrayHasKey( 'upcoming', $scope['choices'] );
		$this->assertArrayHasKey( 'past', $scope['choices'] );
	}

	/**
	 * Existing registry entries are preserved, not replaced.
	 */
	public function test_preserves_existing_fields(): void {
		$existing = [ [ 'key' => 'included_years', 'type' => 'text' ] ];
		$fields   = ( new MeetingDateScope() )->add_field( $existing );

		$this->assertSame( 'included_years', $fields[0]['key'] );
		$this->assertCount( 2, $fields );
	}

	/**
	 * date_scope=upcoming appends a >= today meta clause on the meeting date.
	 */
	public function test_upcoming_restricts_to_today_or_later(): void {
		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'date_scope', 'upcoming' );

		$args   = [ 'meta_query' => [ 'relation' => 'AND' ] ];
		$result = ( new MeetingDateScope() )->apply_date_scope( $args, $request );
		$clause = end( $result['meta_query'] );

		$this->assertSame( 'edbs_meeting_date', $clause['key'] );
		$this->assertSame( wp_date( 'Y-m-d' ), $clause['value'] );
		$this->assertSame( '>=', $clause['compare'] );
		$this->assertSame( 'DATE', $clause['type'] );
	}

	/**
	 * date_scope=past appends a < today meta clause on the meeting date.
	 */
	public function test_past_restricts_to_before_today(): void {
		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'date_scope', 'past' );

		$args   = [ 'meta_query' => [ 'relation' => 'AND' ] ];
		$result = ( new MeetingDateScope() )->apply_date_scope( $args, $request );
		$clause = end( $result['meta_query'] );

		$this->assertSame( 'edbs_meeting_date', $clause['key'] );
		$this->assertSame( wp_date( 'Y-m-d' ), $clause['value'] );
		$this->assertSame( '<', $clause['compare'] );
		$this->assertSame( 'DATE', $clause['type'] );
	}

	/**
	 * A missing scope or the 'all' default leaves the args untouched.
	 */
	public function test_all_or_missing_scope_leaves_args_unchanged(): void {
		$original = [ 'meta_query' => [ 'relation' => 'AND' ] ];

		$request  = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$no_param = ( new MeetingDateScope() )->apply_date_scope( $original, $request );
		$this->assertSame( $original, $no_param );

		$all_request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$all_request->set_param( 'date_scope', 'all' );
		$all = ( new MeetingDateScope() )->apply_date_scope( $original, $all_request );
		$this->assertSame( $original, $all );
	}

	/**
	 * An unrecognized scope value is ignored rather than queryed against.
	 */
	public function test_unknown_scope_is_ignored(): void {
		$original = [ 'meta_query' => [ 'relation' => 'AND' ] ];

		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'date_scope', 'random' );

		$result = ( new MeetingDateScope() )->apply_date_scope( $original, $request );
		$this->assertSame( $original, $result );
	}

	/**
	 * An explicit start_date/end_date takes priority over the scope,
	 * matching how the free endpoint lets an explicit range override
	 * included_years.
	 */
	public function test_explicit_dates_take_priority_over_scope(): void {
		$original = [ 'meta_query' => [ 'relation' => 'AND' ] ];

		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'date_scope', 'upcoming' );
		$request->set_param( 'start_date', '2026-01-01' );

		$result = ( new MeetingDateScope() )->apply_date_scope( $original, $request );
		$this->assertSame( $original, $result );
	}

	/**
	 * A scope applied to args with no meta_query yet creates one rather
	 * than crashing, so the clause always has an AND relation to join.
	 */
	public function test_creates_meta_query_when_absent(): void {
		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'date_scope', 'past' );

		$result = ( new MeetingDateScope() )->apply_date_scope( [], $request );

		$this->assertArrayHasKey( 'meta_query', $result );
		$this->assertSame( 'AND', $result['meta_query']['relation'] );
		$this->assertCount( 2, $result['meta_query'] );
	}

	/**
	 * register() wires the field onto edbs_shortcode_field_registry and
	 * the date filter onto edbs_rest_query_args, so the free plugin's
	 * machinery picks both up. Filters are removed again afterwards to
	 * avoid leaking the callbacks into later tests.
	 */
	public function test_register_wires_both_hooks(): void {
		$component = new MeetingDateScope();
		$component->register();

		$fields = apply_filters( 'edbs_shortcode_field_registry', [] );
		$keys   = wp_list_pluck( $fields, 'key' );
		$this->assertContains( 'date_scope', $keys );

		$request = new WP_REST_Request( 'GET', '/edbs/v1/boardscribe/' );
		$request->set_param( 'date_scope', 'upcoming' );
		$result = apply_filters( 'edbs_rest_query_args', [ 'meta_query' => [ 'relation' => 'AND' ] ], $request );
		$clause = end( $result['meta_query'] );
		$this->assertSame( 'edbs_meeting_date', $clause['key'] );
		$this->assertSame( '>=', $clause['compare'] );

		remove_filter( 'edbs_shortcode_field_registry', [ $component, 'add_field' ] );
		remove_filter( 'edbs_rest_query_args', [ $component, 'apply_date_scope' ] );
	}
}
