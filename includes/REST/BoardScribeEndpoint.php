<?php
/**
 * REST API endpoint for BoardScribe meetings.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;

/**
 * Registers and handles the /edbs/v1/boardscribe/ REST endpoint.
 *
 * This endpoint is intentionally public (permission_callback: __return_true)
 * because meeting agendas and minutes are public records. No authentication is required
 * to read them, just as no authentication is required to view a public archive page.
 */
class BoardScribeEndpoint {

	/**
	 * Hooks REST route registration into WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Registers the custom REST route.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_route(): void {
		// "page" and "include_available_years" are REST-only params, not
		// shortcode/builder/block fields, so they aren't sourced from the
		// field registry.
		$args = [
			'page'                    => [
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
			'include_available_years' => [
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
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
		 * Filters the registered args schema for the boardscribe REST route.
		 * Pro plugin uses this to add REST-only params that aren't backed by any
		 * shortcode/builder/block field (e.g. full-text search) — fields that do
		 * need a builder/block field should be registered on
		 * edbs_shortcode_field_registry with rest_arg => true instead.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args The REST route args schema.
		 */
		$args = apply_filters( 'edbs_rest_route_args', $args );

		register_rest_route(
			'edbs/v1',
			'/boardscribe/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_meetings' ],
				'permission_callback' => '__return_true', // Public data — meeting agendas and minutes are public records.
				'args'                => $args,
			]
		);
	}

	/**
	 * Handles GET requests to the boardscribe endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function get_meetings( \WP_REST_Request $request ): \WP_REST_Response {
		$page                    = $request->get_param( 'page' );
		$include_available_years = $request->get_param( 'include_available_years' );
		$posts_per_page          = $request->get_param( 'posts_per_page' );
		$held_date_format        = $request->get_param( 'held_date_format' );
		$not_held_date_format    = $request->get_param( 'not_held_date_format' );
		$included_years          = $request->get_param( 'included_years' );
		$start_date              = $request->get_param( 'start_date' );
		$end_date                = $request->get_param( 'end_date' );
		$agenda_link_label       = $request->get_param( 'agenda_link_label' ) ? $request->get_param( 'agenda_link_label' ) : __( 'View Agenda', 'boardscribe' );
		$minutes_link_label      = $request->get_param( 'minutes_link_label' ) ? $request->get_param( 'minutes_link_label' ) : __( 'View Minutes', 'boardscribe' );

		$posts_per_page = (int) $posts_per_page;

		if ( -1 === $posts_per_page ) {
			/**
			 * Filters the absolute ceiling applied to "show all" (`-1`) requests.
			 *
			 * The endpoint is public, so even the shortcode builder's explicit
			 * no-limit option must resolve to a bounded query — otherwise any
			 * anonymous caller could request every record in one response.
			 *
			 * @since 1.0.0
			 *
			 * @param int $absolute_max Upper bound substituted for -1. Default 500.
			 */
			$posts_per_page = (int) apply_filters( 'edbs_rest_absolute_max_per_page', 500 );
		} elseif ( $posts_per_page > 0 ) {
			/**
			 * Filters the maximum number of meetings a single REST request may return.
			 *
			 * Bounds arbitrary positive values sent directly to the public endpoint.
			 *
			 * @since 1.0.0
			 *
			 * @param int $max_per_page Maximum posts_per_page. Default 100.
			 */
			$posts_per_page = min( $posts_per_page, (int) apply_filters( 'edbs_rest_max_per_page', 100 ) );
		}

		$args = [
			'post_type'      => 'edbs_meeting',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'meta_key'       => 'edbs_meeting_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required to order results by meeting date.
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to filter to posts that have a meeting date set.
				'relation' => 'AND',
				[
					'key'     => 'edbs_meeting_date',
					'compare' => 'EXISTS',
				],
			],
		];

		if ( ! empty( $start_date ) || ! empty( $end_date ) ) {
			// start_date/end_date support arbitrary ranges (e.g. a
			// July-to-June fiscal year) that included_years can't express,
			// so they take priority over it entirely rather than being
			// combined — a caller sending both almost certainly means the
			// explicit range, not an intersection with whole calendar years.
			if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
				$args['meta_query'][] = [
					'key'     => 'edbs_meeting_date',
					'value'   => [ $start_date, $end_date ],
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				];
			} elseif ( ! empty( $start_date ) ) {
				$args['meta_query'][] = [
					'key'     => 'edbs_meeting_date',
					'value'   => $start_date,
					'compare' => '>=',
					'type'    => 'DATE',
				];
			} else {
				$args['meta_query'][] = [
					'key'     => 'edbs_meeting_date',
					'value'   => $end_date,
					'compare' => '<=',
					'type'    => 'DATE',
				];
			}
		} elseif ( ! empty( $included_years ) ) {
			$years        = explode( ',', $included_years );
			$year_queries = [ 'relation' => 'OR' ];

			foreach ( $years as $year ) {
				$year           = intval( $year );
				$year_queries[] = [
					'key'     => 'edbs_meeting_date',
					'value'   => [ $year . '-01-01', $year . '-12-31' ],
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				];
			}

			$args['meta_query'][] = $year_queries;
		}

		/**
		 * Filters the WP_Query args before querying meetings.
		 * Pro plugin can add taxonomy queries, additional meta filters, etc.
		 *
		 * @since 1.0.0
		 *
		 * @param array            $args    The WP_Query args.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$args = apply_filters( 'edbs_rest_query_args', $args, $request );

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

		if ( $include_available_years ) {
			// Independent of $args' included_years/start_date/end_date
			// filtering above — a year switcher needs every year that has
			// data, not just the currently-selected one.
			$response['available_years'] = $this->get_available_years();
		}

		/**
		 * Filters the full REST response before it is returned.
		 * Pro plugin can add top-level fields (e.g., available categories).
		 *
		 * @since 1.0.0
		 *
		 * @param array            $response The response data.
		 * @param \WP_REST_Request $request  The REST request.
		 */
		$response = apply_filters( 'edbs_rest_response', $response, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Returns the distinct calendar years that have at least one published
	 * meeting with a date set, newest first.
	 *
	 * Not scoped by get_meetings()'s date filters (included_years/
	 * start_date/end_date) — a year switcher needs every year with data.
	 * It is scoped by edbs_rest_query_args, so Pro's taxonomy/meta
	 * constraints still apply.
	 *
	 * @since 1.0.0
	 *
	 * @return int[] Years, e.g. [ 2026, 2025, 2023 ].
	 */
	private function get_available_years(): array {
		$args = [
			'post_type'      => 'edbs_meeting',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => 'edbs_meeting_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required to filter to posts that have a meeting date set.
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- required to filter to posts that have a meeting date set.
				[
					'key'     => 'edbs_meeting_date',
					'compare' => 'EXISTS',
				],
			],
		];

		// Reuses get_meetings()'s own filter, with $request null since
		// this isn't a real REST dispatch.
		$args = apply_filters( 'edbs_rest_query_args', $args, null );

		// Not a paginated list — a Pro callback's pagination/order keys
		// shouldn't carry over here.
		unset( $args['paged'], $args['orderby'], $args['order'] );
		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';
		$args['no_found_rows']  = true;

		$years = [];
		foreach ( get_posts( $args ) as $post_id ) {
			// parse_date(), not SQL YEAR() — handles legacy d/m/Y and
			// m/d/Y meta values MySQL's YEAR() can't parse.
			$date_object = self::parse_date( (string) get_post_meta( $post_id, 'edbs_meeting_date', true ) );
			if ( $date_object ) {
				$years[ (int) $date_object->format( 'Y' ) ] = true;
			}
		}

		$years = array_keys( $years );
		rsort( $years );

		return $years;
	}

	/**
	 * Builds the public-facing row data for a single meeting post.
	 *
	 * Extracted so Pro plugin features that need the exact same
	 * escaped/formatted output as this endpoint (CSV/PDF export, an iCal
	 * feed, a "most recent meeting" widget) can call this directly instead
	 * of re-implementing the same escaping logic themselves.
	 *
	 * @since 1.0.0
	 *
	 * @param int                   $post_id     The meeting post ID.
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
		$held_date_format     = $format_args['held_date_format'] ?? 'l, F j, Y';
		$not_held_date_format = $format_args['not_held_date_format'] ?? 'F Y';
		$agenda_link_label    = ! empty( $format_args['agenda_link_label'] ) ? $format_args['agenda_link_label'] : __( 'View Agenda', 'boardscribe' );
		$minutes_link_label   = ! empty( $format_args['minutes_link_label'] ) ? $format_args['minutes_link_label'] : __( 'View Minutes', 'boardscribe' );

		$meeting_date     = get_post_meta( $post_id, 'edbs_meeting_date', true );
		$meeting_not_held = (bool) get_post_meta( $post_id, 'edbs_meeting_not_held', true );
		$agenda_url       = get_post_meta( $post_id, 'edbs_agenda_url', true );
		$minutes_url      = get_post_meta( $post_id, 'edbs_minutes_url', true );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional, gated behind WP_DEBUG for troubleshooting date parsing.
			error_log( 'EDBS: Raw meeting date for post ' . $post_id . ': ' . $meeting_date );
		}

		$date_object    = self::parse_date( $meeting_date );
		$formatted_date = $date_object
			? esc_html( $date_object->format( $meeting_not_held ? $not_held_date_format : $held_date_format ) )
			: '<span class="sr-text screen-reader-text">' . esc_html__( 'Date not available', 'boardscribe' ) . '</span>';

		/**
		 * Filters the formatted date string before it's used in the visible date
		 * cell and in the agenda/minutes link aria-labels below. Pro plugin uses
		 * this to substitute a per-meeting text override (e.g. "March Special",
		 * "TBD") for the computed date, honored whenever set regardless of
		 * held/not-held status. Sort order is unaffected — it's driven by the
		 * raw edbs_meeting_date value, not this display string.
		 *
		 * @since 1.0.0
		 *
		 * @param string $formatted_date   The computed/escaped date display string.
		 * @param int    $post_id          The post ID.
		 * @param bool   $meeting_not_held Whether the meeting is marked not held.
		 */
		$filtered_date  = apply_filters( 'edbs_meeting_formatted_date', $formatted_date, $post_id, $meeting_not_held );
		$formatted_date = is_string( $filtered_date ) ? wp_kses_post( $filtered_date ) : $formatted_date;

		$agenda_item = $agenda_url
			? apply_filters(
				'edbs_agenda_link',
				'<a href="' . esc_url( $agenda_url ) . '" aria-label="' . esc_attr(
					sprintf(
					/* translators: 1: link label e.g. "View Agenda", 2: meeting date */
						__( '%1$s for %2$s', 'boardscribe' ),
						$agenda_link_label,
						wp_strip_all_tags( $formatted_date )
					)
				) . '">' . esc_html( $agenda_link_label ) . '</a>'
			)
			: '<span class="sr-text screen-reader-text">' . esc_html__( 'Agenda not available', 'boardscribe' ) . '</span>';

		$minutes_item = $minutes_url
			? apply_filters(
				'edbs_minutes_link',
				'<a href="' . esc_url( $minutes_url ) . '" aria-label="' . esc_attr(
					sprintf(
					/* translators: 1: link label e.g. "View Minutes", 2: meeting date */
						__( '%1$s for %2$s', 'boardscribe' ),
						$minutes_link_label,
						wp_strip_all_tags( $formatted_date )
					)
				) . '">' . esc_html( $minutes_link_label ) . '</a>'
			)
			: '<span class="sr-text screen-reader-text">' . esc_html__( 'Minutes not available', 'boardscribe' ) . '</span>';

		$row = [
			'title'   => esc_html( get_the_title( $post_id ) ),
			'date'    => $formatted_date,
			'agenda'  => $meeting_not_held ? esc_html__( 'Meeting not held', 'boardscribe' ) : $agenda_item,
			'minutes' => $minutes_item,
		];

		/**
		 * Filters a single meeting row before it is added to the response.
		 * Pro plugin can add extra fields (e.g., category, attachments).
		 *
		 * @since 1.0.0
		 *
		 * @param array                  $row     The meeting row data.
		 * @param int                    $post_id The post ID.
		 * @param \WP_REST_Request|null $request  The REST request, if any.
		 */
		return apply_filters( 'edbs_meeting_row_data', $row, $post_id, $request );
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
	 * @since 1.0.0
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
	 * Public and static so Pro features that derive values from the raw
	 * edbs_meeting_date meta (e.g. year grouping) parse the stored value
	 * with the exact same format list as this endpoint, instead of
	 * maintaining a copy that could silently diverge.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_string The raw date string from post meta.
	 * @return \DateTime|null
	 */
	public static function parse_date( string $date_string ): ?\DateTime {
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
