<?php
/**
 * REST API endpoint for meeting minutes.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\REST;

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
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Registers the custom REST route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route( 'edmm/v1', '/meeting-minutes/', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_meeting_minutes' ],
			'permission_callback' => '__return_true', // Public data — meeting minutes are public records.
			'args'                => [
				'page'                => [
					'default'           => 1,
					'sanitize_callback' => 'absint',
				],
				'posts_per_page'      => [
					'default'           => 20,
					'sanitize_callback' => 'absint',
				],
				'held_date_format'    => [
					'default'           => 'Y/m/d',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'not_held_date_format' => [
					'default'           => 'Y/m',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'included_years'      => [
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'agenda_link_label'   => [
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'notes_link_label'    => [
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	/**
	 * Handles GET requests to the meeting minutes endpoint.
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
		$agenda_link_label    = $request->get_param( 'agenda_link_label' ) ?: __( 'View Agenda', 'edmm' );
		$notes_link_label     = $request->get_param( 'notes_link_label' ) ?: __( 'View Notes', 'edmm' );

		$args = [
			'post_type'      => 'edmm_meeting_minutes',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'meta_key'       => 'edmm_meeting_date',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => [
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
				$year = intval( $year );
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
		 * @param array            $args    The WP_Query args.
		 * @param \WP_REST_Request $request The REST request.
		 */
		$args = apply_filters( 'edmm_rest_query_args', $args, $request );

		$query    = new \WP_Query( $args );
		$meetings = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				$meeting_date       = get_post_meta( $post_id, 'edmm_meeting_date', true );
				$meeting_not_held   = (bool) get_post_meta( $post_id, 'edmm_meeting_not_held', true );
				$meeting_agenda_url = get_post_meta( $post_id, 'edmm_meeting_agenda_url', true );
				$meeting_notes_url  = get_post_meta( $post_id, 'edmm_meeting_notes_url', true );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'EDMM: Raw meeting date for post ' . $post_id . ': ' . $meeting_date );
				}

				$date_object    = $this->parse_date( $meeting_date );
				$formatted_date = $date_object
					? $date_object->format( $meeting_not_held ? $not_held_date_format : $held_date_format )
					: '<span class="sr-text screen-reader-text">' . esc_html__( 'Date not available', 'edmm' ) . '</span>';

				$agenda_item = $meeting_agenda_url
					? apply_filters(
						'edmm_meeting_agenda_link',
						'<a href="' . esc_url( $meeting_agenda_url ) . '" aria-label="' . esc_attr( sprintf(
							/* translators: 1: link label e.g. "View Agenda", 2: meeting date */
							__( '%1$s for %2$s', 'edmm' ),
							$agenda_link_label,
							wp_strip_all_tags( $formatted_date )
						) ) . '">' . esc_html( $agenda_link_label ) . '</a>'
					)
					: '<span class="sr-text screen-reader-text">' . esc_html__( 'Agenda not available', 'edmm' ) . '</span>';

				$notes_item = $meeting_notes_url
					? apply_filters(
						'edmm_meeting_notes_link',
						'<a href="' . esc_url( $meeting_notes_url ) . '" aria-label="' . esc_attr( sprintf(
							/* translators: 1: link label e.g. "View Notes", 2: meeting date */
							__( '%1$s for %2$s', 'edmm' ),
							$notes_link_label,
							wp_strip_all_tags( $formatted_date )
						) ) . '">' . esc_html( $notes_link_label ) . '</a>'
					)
					: '<span class="sr-text screen-reader-text">' . esc_html__( 'Notes not available', 'edmm' ) . '</span>';

				$row = [
					'title'  => get_the_title(),
					'date'   => $formatted_date,
					'agenda' => $meeting_not_held ? esc_html__( 'Meeting not held', 'edmm' ) : $agenda_item,
					'notes'  => $notes_item,
				];

				/**
				 * Filters a single meeting row before it is added to the response.
				 * Pro plugin can add extra fields (e.g., category, attachments).
				 *
				 * @param array    $row     The meeting row data.
				 * @param int      $post_id The post ID.
				 * @param \WP_REST_Request $request The REST request.
				 */
				$meetings[] = apply_filters( 'edmm_meeting_row_data', $row, $post_id, $request );
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
		 * @param array            $response The response data.
		 * @param \WP_REST_Request $request  The REST request.
		 */
		$response = apply_filters( 'edmm_rest_response', $response, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Attempts to parse a date string using multiple common formats.
	 *
	 * Supports Y-m-d (native/ISO), Ymd (ACF default), d/m/Y and m/d/Y
	 * to handle data previously stored by ACF with varying format settings.
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
			if ( $date !== false ) {
				return $date;
			}
		}

		return null;
	}
}
