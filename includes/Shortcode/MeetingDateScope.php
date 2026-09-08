<?php
/**
 * Upcoming/past date scope for the meetings shortcode/block/REST endpoint.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds an upcoming/past date-scope option to the meetings shortcode,
 * block and REST endpoint.
 *
 * The endpoint only accepts literal start_date/end_date strings, so
 * "from today forward" cannot be expressed without hand-editing the
 * date every month. This registers a `date_scope` field (all/upcoming/
 * past, default all) on the field registry, which surfaces it as a
 * shortcode attribute, block control and builder field, and translates
 * the chosen scope into a date-bounds meta query on the endpoint's
 * WP_Query args via edbs_rest_query_args.
 *
 * @since x.x.x
 */
class MeetingDateScope {

	/**
	 * The field key registered on edbs_shortcode_field_registry.
	 *
	 * @var string
	 */
	const FIELD_KEY = 'date_scope';

	/**
	 * Hooks the date-scope field and REST arg into the field registry and
	 * REST endpoint. Registered from Plugin::boot().
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'edbs_shortcode_field_registry', [ $this, 'add_field' ] );
		add_filter( 'edbs_rest_query_args', [ $this, 'apply_date_scope' ], 10, 2 );
	}

	/**
	 * Adds the `date_scope` field descriptor to the field registry.
	 *
	 * The registry drives the shortcode attribute list, the instance config,
	 * the Gutenberg block attribute + sidebar control, and the shortcode
	 * builder UI. rest_arg is true so the endpoint can read it off the
	 * request; apply_date_scope() whitelists the scope before it ever
	 * touches the query.
	 *
	 * @since x.x.x
	 *
	 * @param array $fields Field descriptors contributed by earlier-priority callbacks.
	 * @return array
	 */
	public function add_field( array $fields ): array {
		$fields[] = [
			'key'         => self::FIELD_KEY,
			'type'        => FieldRegistry::TYPE_SELECT,
			'group'       => 'general',
			'label'       => __( 'Date scope', 'boardscribe' ),
			'description' => __( 'All meetings is the full archive. Upcoming shows only meetings from today onward; Past shows only meetings already held.', 'boardscribe' ),
			'default'     => 'all',
			'choices'     => [
				'all'      => __( 'All meetings', 'boardscribe' ),
				'upcoming' => __( 'Upcoming meetings', 'boardscribe' ),
				'past'     => __( 'Past meetings', 'boardscribe' ),
			],
			'rest_arg'    => true,
		];

		return $fields;
	}

	/**
	 * Applies the requested date scope to the endpoint's WP_Query args.
	 *
	 * Upcoming resolves to meetings held from today onward (date >= today),
	 * past to meetings held before today (date < today), compared as dates
	 * against the edbs_meeting_date meta in the site's own timezone.
	 *
	 * An explicit start_date/end_date on the request always takes priority
	 * over the scope, matching how the endpoint already lets an explicit
	 * range override included_years. Anything other than a bare
	 * 'upcoming'/'past' scope (including the 'all' default and an absent
	 * param) leaves the args untouched.
	 *
	 * @since x.x.x
	 *
	 * @param array            $args    The WP_Query args.
	 * @param \WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function apply_date_scope( array $args, \WP_REST_Request $request ): array {
		$scope = $request->get_param( self::FIELD_KEY );

		if ( 'upcoming' !== $scope && 'past' !== $scope ) {
			return $args;
		}

		if ( $request->get_param( 'start_date' ) || $request->get_param( 'end_date' ) ) {
			return $args;
		}

		if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = [ 'relation' => 'AND' ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to restrict meetings to the requested date scope.
		}

		$args['meta_query'][] = [
			'key'     => 'edbs_meeting_date',
			'value'   => wp_date( 'Y-m-d' ),
			'compare' => 'upcoming' === $scope ? '>=' : '<',
			'type'    => 'DATE',
		];

		return $args;
	}
}
