<?php
/**
 * Keeps a meeting from being published without a valid meeting date.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The meeting date is the one required field. A draft may be saved without
 * one, but nothing may be published or scheduled until it has a valid
 * Y-m-d date.
 *
 * The block editor saves the post over REST first and the Meeting Details
 * meta box (which holds the date) afterwards, so a REST publish can't be
 * rejected for a missing stored date. The meta box save is where a
 * dateless publish is reverted to draft; REST requests that carry an
 * explicit date are validated up front.
 */
class MeetingDateGuard {

	const META_KEY = 'edbs_meeting_date';

	/**
	 * Whether a revert is in progress, so its own wp_update_post() doesn't
	 * re-enter the meta box save and revert again.
	 *
	 * @var bool
	 */
	private $reverting = false;

	/**
	 * Hooks the guard into WordPress.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'rest_pre_insert_edbs_meeting', [ $this, 'validate_rest_publish' ], 10, 2 );
		add_action( 'edbs_save_meeting_meta', [ $this, 'revert_dateless_publish' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
	}

	/**
	 * Whether a value is a real Y-m-d calendar date.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Candidate date.
	 * @return bool
	 */
	public static function is_valid_date( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$date = \DateTime::createFromFormat( 'Y-m-d', $value );

		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Meta sanitize callback: anything that isn't a valid Y-m-d date is
	 * stored as an empty string rather than as garbage.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value Raw meta value.
	 * @return string
	 */
	public static function sanitize_date( $value ): string {
		$value = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';

		return self::is_valid_date( $value ) ? $value : '';
	}

	/**
	 * Rejects a REST request that publishes a meeting while explicitly
	 * sending a missing or invalid date.
	 *
	 * @since 1.2.0
	 *
	 * @param \stdClass        $prepared_post Prepared post data.
	 * @param \WP_REST_Request $request       The request.
	 * @return \stdClass|\WP_Error
	 */
	public function validate_rest_publish( $prepared_post, $request ) {
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) || ! array_key_exists( self::META_KEY, $meta ) ) {
			return $prepared_post;
		}

		$status = $request->get_param( 'status' );
		if ( ! $status && ! empty( $prepared_post->ID ) ) {
			$status = get_post_status( $prepared_post->ID );
		}

		if ( self::is_publishing_status( (string) $status ) && ! self::is_valid_date( $meta[ self::META_KEY ] ) ) {
			return new \WP_Error(
				'edbs_meeting_date_required',
				__( 'A meeting needs a valid meeting date (YYYY-MM-DD) before it can be published.', 'boardscribe' ),
				[ 'status' => 400 ]
			);
		}

		return $prepared_post;
	}

	/**
	 * Reverts a published or scheduled meeting to draft when the meta box
	 * save leaves it without a valid date.
	 *
	 * @since 1.2.0
	 *
	 * @param int $post_id The meeting's post ID.
	 * @return void
	 */
	public function revert_dateless_publish( $post_id ): void {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( $this->reverting || ! $post || ! self::is_publishing_status( $post->post_status ) ) {
			return;
		}

		if ( self::is_valid_date( get_post_meta( $post_id, self::META_KEY, true ) ) ) {
			return;
		}

		$this->reverting = true;
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);
		$this->reverting = false;

		set_transient( $this->notice_key(), $post_id, MINUTE_IN_SECONDS );
	}

	/**
	 * Shows the revert notice on the next screen load, and flags any
	 * meetings already published without a valid date on the list screen.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function render_notices(): void {
		$reverted = get_transient( $this->notice_key() );
		if ( $reverted ) {
			delete_transient( $this->notice_key() );
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'The meeting was saved as a draft because it has no meeting date. Add a date, then publish it.', 'boardscribe' )
			);
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-edbs_meeting' !== $screen->id ) {
			return;
		}

		$count = $this->count_published_without_date();
		if ( $count > 0 ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of meetings. */
						_n(
							'%d published meeting has no valid meeting date. Add a date to it.',
							'%d published meetings have no valid meeting date. Add a date to each.',
							$count,
							'boardscribe'
						),
						$count
					)
				)
			);
		}
	}

	/**
	 * Counts published or scheduled meetings with no stored date.
	 *
	 * @since 1.2.0
	 *
	 * @return int
	 */
	private function count_published_without_date(): int {
		$query = new \WP_Query(
			[
				'post_type'      => 'edbs_meeting',
				'post_status'    => [ 'publish', 'future' ],
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only count on one screen.
					'relation' => 'OR',
					[
						'key'     => self::META_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => self::META_KEY,
						'value'   => '',
						'compare' => '=',
					],
				],
			]
		);

		return (int) $query->found_posts;
	}

	/**
	 * Per-user transient key for the revert notice.
	 *
	 * @return string
	 */
	private function notice_key(): string {
		return 'edbs_date_required_notice_' . get_current_user_id();
	}

	/**
	 * Whether a post status makes the meeting public or scheduled.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private static function is_publishing_status( string $status ): bool {
		return in_array( $status, [ 'publish', 'future' ], true );
	}
}
