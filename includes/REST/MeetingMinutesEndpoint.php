<?php
/**
 * REST API endpoint for meeting minutes.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\REST;

use EqualizeDigital\MeetingMinutes\Shortcode\FieldRegistry;

/**
 * Registers and handles the /edmm/v1/meeting-minutes/ REST endpoint.
 *
 * This endpoint is intentionally public (permission_callback: __return_true)
 * because meeting minutes are public records. No authentication is required
 * to read them, just as no authentication is required to view a public archive page.
 */
class MeetingMinutesEndpoint {

	/**
	 * Hooks REST route registration into WordPress.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Registers the custom REST route.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register_route(): void {
		// "page" is REST-only pagination, not a shortcode/builder/block
		// field, so it isn't sourced from the field registry.
		$args = [
			'page' => [
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
		];

		// Every field the registry flags rest_arg => true (shared with the
		// shortcode attribute / instance config it's built from — see
		// FieldRegistry::resolve_value()) becomes a REST route arg too.
		foreach ( FieldRegistry::all() as $field ) {
			if ( empty( $field['rest_arg'] ) ) {
				continue;
			}

			$args[ $field['key'] ] = [
				'default'           => $field['default'] ?? '',
				'sanitize_callback' => static function ( $value ) use ( $field ) {
					return FieldRegistry::resolve_value( $field, $value );
				},
			];

			if ( ! empty( $field['validate_callback'] ) ) {
				$args[ $field['key'] ]['validate_callback'] = $field['validate_callback'];
			}
		}

		/**
		 * Filters the registered args schema for the meeting-minutes REST route.
		 * Pro plugin uses this to add REST-only params that aren't backed by any
		 * shortcode/builder/block field (e.g. full-text search) — fields that do
		 * need a builder/block field should be registered on
		 * edmm_shortcode_field_registry with rest_arg => true instead.
		 *
		 * @since x.x.x
		 *
		 * @param array $args The REST route args schema.
		 */
		$args = apply_filters( 'edmm_rest_route_args', $args );

		register_rest_route(
			'edmm/v1',
			'/meeting-minutes/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_meeting_minutes' ],
				'permission_callback' => '__return_true', // Public data — meeting minutes are public records.
				'args'                => $args,
			]
		);
	}

	/**
	 * Handles GET requests to the meeting minutes endpoint.
	 *
	 * @since x.x.x
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function get_meeting_minutes( \WP_REST_Request $request ): \WP_REST_Response {
		$page                 = $request->get_param( 'page' );
		$posts_per_page       = $request->get_param( 'posts_per_page' );
		$held_date_format     = $request->get_param( 'held_date_format' );
		$not_held_date_format = $request->get_param( 'not_held_date_format' );
		$included_years       = $request->get_param( 'included_years' );
		$agenda_link_label    = $request->get_param( 'agenda_link_label' ) ? $request->get_param( 'agenda_link_label' ) : __( 'View Agenda', 'edmm' );
		$minutes_link_label   = $request->get_param( 'minutes_link_label' ) ? $request->get_param( 'minutes_link_label' ) : __( 'View Minutes', 'edmm' );

		$args = [
			'post_type'      => 'edmm_meeting_minutes',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'meta_key'       => 'edmm_meeting_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required to order results by meeting date.
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to filter to posts that have a meeting date set.
				'relation' => 'AND',
				[
					'key'     => 'edmm_meeting_date',
					'compare' => 'EXISTS',
				],
			],
		];

		if ( ! empty( $included_years ) ) {
			$years        = explode( ',', $included_years );
			$year_queries = [ 'relation' => 'OR' ];

			foreach ( $years as $year ) {
				$year           = intval( $year );
				$year_queries[] = [
					'key'     => 'edmm_meeting_date',
					'value'   => [ $year . '-01-01', $year . '-12-31' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				];
			}

			$args['meta_query'][] = $year_queries;
		}

		/**
		 * Filters the WP_Query args before querying meeting minutes.
		 * Pro plugin can add taxonomy queries, additional meta filters, etc.
		 *
		 * @since x.x.x
		 *
		 * @param array            $args    The WP_Query args.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$args = apply_filters( 'edmm_rest_query_args', $args, $request );

		$query    = new \WP_Query( $args );
		$meetings = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				$meetings[] = $this->build_meeting_row(
					get_the_ID(),
					[
						'held_date_format'     => $held_date_format,
						'not_held_date_format' => $not_held_date_format,
						'agenda_link_label'    => $agenda_link_label,
						'minutes_link_label'   => $minutes_link_label,
					],
					$request
				);
			}
			wp_reset_postdata();
		}

		$response = [
			'meetings'      => $meetings,
			'max_num_pages' => $query->max_num_pages,
			'current_page'  => $page,
			'total_entries' => $query->found_posts,
		];

		/**
		 * Filters the full REST response before it is returned.
		 * Pro plugin can add top-level fields (e.g., available categories).
		 *
		 * @since x.x.x
		 *
		 * @param array            $response The response data.
		 * @param \WP_REST_Request $request  The REST request.
		 */
		$response = apply_filters( 'edmm_rest_response', $response, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Builds the public-facing row data for a single meeting minutes post.
	 *
	 * Extracted so Pro plugin features that need the exact same
	 * escaped/formatted output as this endpoint (CSV/PDF export, an iCal
	 * feed, a "most recent meeting" widget) can call this directly instead
	 * of re-implementing the same escaping logic themselves.
	 *
	 * @since x.x.x
	 *
	 * @param int                   $post_id     The meeting minutes post ID.
	 * @param array                 $format_args {
	 *    Formatting options.
	 *
	 *     @type string $held_date_format     PHP date() format for held meetings.
	 *     @type string $not_held_date_format PHP date() format for not-held meetings.
	 *     @type string $agenda_link_label    Link text for the agenda link.
	 *     @type string $minutes_link_label   Link text for the minutes link.
	 * }
	 * @param \WP_REST_Request|null $request     The originating REST request, if any.
	 * @return array
	 */
	public function build_meeting_row( int $post_id, array $format_args, ?\WP_REST_Request $request = null ): array {
		$held_date_format     = $format_args['held_date_format'] ?? 'Y/m/d';
		$not_held_date_format = $format_args['not_held_date_format'] ?? 'Y/m';
		$agenda_link_label    = ! empty( $format_args['agenda_link_label'] ) ? $format_args['agenda_link_label'] : __( 'View Agenda', 'edmm' );
		$minutes_link_label   = ! empty( $format_args['minutes_link_label'] ) ? $format_args['minutes_link_label'] : __( 'View Minutes', 'edmm' );

		$meeting_date        = get_post_meta( $post_id, 'edmm_meeting_date', true );
		$meeting_not_held    = (bool) get_post_meta( $post_id, 'edmm_meeting_not_held', true );
		$meeting_agenda_url  = get_post_meta( $post_id, 'edmm_meeting_agenda_url', true );
		$meeting_minutes_url = get_post_meta( $post_id, 'edmm_meeting_minutes_url', true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional, gated behind WP_DEBUG for troubleshooting date parsing.
			error_log( 'EDMM: Raw meeting date for post ' . $post_id . ': ' . $meeting_date );
		}

		$date_object    = $this->parse_date( $meeting_date );
		$formatted_date = $date_object
			? esc_html( $date_object->format( $meeting_not_held ? $not_held_date_format : $held_date_format ) )
			: '<span class="sr-text screen-reader-text">' . esc_html__( 'Date not available', 'edmm' ) . '</span>';

		/**
		 * Filters the formatted date string before it's used in the visible date
		 * cell and in the agenda/minutes link aria-labels below. Pro plugin uses
		 * this to substitute a per-meeting text override (e.g. "March Special",
		 * "TBD") for the computed date, honored whenever set regardless of
		 * held/not-held status. Sort order is unaffected — it's driven by the
		 * raw edmm_meeting_date value, not this display string.
		 *
		 * @since x.x.x
		 *
		 * @param string $formatted_date   The computed/escaped date display string.
		 * @param int    $post_id          The post ID.
		 * @param bool   $meeting_not_held Whether the meeting is marked not held.
		 */
		$filtered_date  = apply_filters( 'edmm_meeting_formatted_date', $formatted_date, $post_id, $meeting_not_held );
		$formatted_date = is_string( $filtered_date ) ? wp_kses_post( $filtered_date ) : $formatted_date;

		$agenda_item = $meeting_agenda_url
			? apply_filters(
				'edmm_meeting_agenda_link',
				'<a href="' . esc_url( $meeting_agenda_url ) . '" aria-label="' . esc_attr(
					sprintf(
					/* translators: 1: link label e.g. "View Agenda", 2: meeting date */
						__( '%1$s for %2$s', 'edmm' ),
						$agenda_link_label,
						wp_strip_all_tags( $formatted_date )
					)
				) . '">' . esc_html( $agenda_link_label ) . '</a>'
			)
			: '<span class="sr-text screen-reader-text">' . esc_html__( 'Agenda not available', 'edmm' ) . '</span>';

		$minutes_item = $meeting_minutes_url
			? apply_filters(
				'edmm_meeting_minutes_link',
				'<a href="' . esc_url( $meeting_minutes_url ) . '" aria-label="' . esc_attr(
					sprintf(
					/* translators: 1: link label e.g. "View Minutes", 2: meeting date */
						__( '%1$s for %2$s', 'edmm' ),
						$minutes_link_label,
						wp_strip_all_tags( $formatted_date )
					)
				) . '">' . esc_html( $minutes_link_label ) . '</a>'
			)
			: '<span class="sr-text screen-reader-text">' . esc_html__( 'Minutes not available', 'edmm' ) . '</span>';

		$row = [
			'title'   => esc_html( get_the_title( $post_id ) ),
			'date'    => $formatted_date,
			'agenda'  => $meeting_not_held ? esc_html__( 'Meeting not held', 'edmm' ) : $agenda_item,
			'minutes' => $minutes_item,
		];

		/**
		 * Filters a single meeting row before it is added to the response.
		 * Pro plugin can add extra fields (e.g., category, attachments).
		 *
		 * @since x.x.x
		 *
		 * @param array                  $row     The meeting row data.
		 * @param int                    $post_id The post ID.
		 * @param \WP_REST_Request|null $request  The REST request, if any.
		 */
		return apply_filters( 'edmm_meeting_row_data', $row, $post_id, $request );
	}

	/**
	 * Validates that a date format string only contains recognized PHP
	 * date() format characters and safe literal separators.
	 *
	 * Rejects backslashes so a caller cannot force otherwise-reserved
	 * format characters to be output as literal text via DateTime's
	 * escape syntax. Also caps the length to avoid feeding pathologically
	 * long strings into DateTime::format() once per result row.
	 *
	 * Requires at least one character: an empty string would otherwise
	 * pass (trivially matching zero repetitions) and silently produce a
	 * blank date instead of falling back to the field's own default -
	 * notably when sanitize_text_field() strips a disallowed value like
	 * "<script>" down to "" before it ever reaches this check.
	 *
	 * No type hint on $value: REST validate_callbacks can receive
	 * non-string raw request values (e.g. an array from a repeated query
	 * param), and a strict type hint would throw a TypeError instead of
	 * just failing validation.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value The raw held_date_format/not_held_date_format value.
	 * @return bool
	 */
	public static function validate_date_format( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}
		return strlen( $value ) <= 32 && (bool) preg_match( '/^[A-Za-z0-9\/\-.: ,]+$/', $value );
	}

	/**
	 * Attempts to parse a date string using multiple common formats.
	 *
	 * Supports Y-m-d (native/ISO), Ymd (ACF default), d/m/Y and m/d/Y
	 * to handle data previously stored by ACF with varying format settings.
	 *
	 * @since x.x.x
	 *
	 * @param string $date_string The raw date string from post meta.
	 * @return \DateTime|null
	 */
	private function parse_date( string $date_string ): ?\DateTime {
		if ( empty( $date_string ) ) {
			return null;
		}

		foreach ( [ 'Y-m-d', 'Ymd', 'd/m/Y', 'm/d/Y' ] as $format ) {
			$date = \DateTime::createFromFormat( $format, $date_string );
			if ( false !== $date ) {
				return $date;
			}
		}

		return null;
	}
}
