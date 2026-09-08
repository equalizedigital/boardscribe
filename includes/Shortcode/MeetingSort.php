<?php
/**
 * Sort order for the meetings shortcode/block/REST endpoint.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds an ascending/descending sort-order option to the meetings shortcode,
 * block and REST endpoint.
 *
 * The endpoint hardcodes newest-first ordering (orderby => meta_value,
 * order => DESC on edbs_meeting_date) - the right default for an archive
 * of past minutes, but wrong for a forward-looking list (an "upcoming
 * meetings" table should show the soonest meeting first). This registers
 * an `order` field (asc/desc, default desc) on the field registry, which
 * surfaces it as a shortcode attribute, block control and builder field,
 * and applies the chosen order to the endpoint's WP_Query args via
 * edbs_rest_query_args.
 *
 * @since x.x.x
 */
class MeetingSort {

	/**
	 * The field key registered on edbs_shortcode_field_registry.
	 *
	 * @var string
	 */
	const FIELD_KEY = 'order';

	/**
	 * Hooks the sort field and REST arg into the field registry and REST
	 * endpoint. Registered from Plugin::boot().
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'edbs_shortcode_field_registry', [ $this, 'add_field' ] );
		add_filter( 'edbs_rest_query_args', [ $this, 'apply_order' ], 10, 2 );
	}

	/**
	 * Adds the `order` field descriptor to the field registry.
	 *
	 * The registry drives the shortcode attribute list, the instance config,
	 * the Gutenberg block attribute + sidebar control, and the shortcode
	 * builder UI. rest_arg is true so the endpoint can read it off the
	 * request; the field's own TYPE_SELECT resolver sanitizes via
	 * sanitize_key(), and apply_order() whitelists ASC/DESC before it ever
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
			'label'       => __( 'Sort order', 'boardscribe' ),
			'description' => __( 'Newest first is the default and suits an archive of past minutes. Choose Oldest first for a forward-looking list such as upcoming meetings.', 'boardscribe' ),
			'default'     => 'desc',
			'choices'     => [
				'desc' => __( 'Newest first', 'boardscribe' ),
				'asc'  => __( 'Oldest first', 'boardscribe' ),
			],
			'rest_arg'    => true,
		];

		return $fields;
	}

	/**
	 * Applies the requested sort order to the endpoint's WP_Query args.
	 *
	 * The endpoint builds its args with order DESC hardcoded; this flips
	 * to ASC when the request carries order=asc. Anything other than a bare
	 * 'asc'/'desc' (including an absent param) is ignored, so the endpoint
	 * keeps its newest-first default for anonymous callers.
	 *
	 * @since x.x.x
	 *
	 * @param array            $args    The WP_Query args.
	 * @param \WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function apply_order( array $args, \WP_REST_Request $request ): array {
		$order = strtolower( (string) $request->get_param( self::FIELD_KEY ) );

		if ( 'asc' === $order || 'desc' === $order ) {
			$args['order'] = strtoupper( $order );
		}

		return $args;
	}
}
