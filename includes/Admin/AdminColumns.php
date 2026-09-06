<?php
/**
 * Admin list table columns for the edbs_meeting custom post type.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Admin;

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Meeting Date, Canceled, Agenda, and Minutes columns to the Board
 * Meetings admin list table, and makes the date column sortable.
 */
class AdminColumns {

	/**
	 * Hooks the admin columns into WordPress.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_edbs_meeting_posts_columns', [ $this, 'add_columns' ] );
		add_action( 'manage_edbs_meeting_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-edbs_meeting_sortable_columns', [ $this, 'sortable_columns' ] );
		add_action( 'pre_get_posts', [ $this, 'sort_by_meeting_date' ] );
	}

	/**
	 * Returns the columns this class adds to the list table, keyed by
	 * column key.
	 *
	 * Filterable so Pro plugin can add its own columns (e.g. location,
	 * category, linked documents) using the same shape - the admin-list-table
	 * analog of edbs_block_preview_columns.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array{label: string, render_cell: callable}>
	 */
	public function get_columns_config(): array {
		$columns = [
			'edbs_meeting_date'     => [
				'label'       => __( 'Meeting Date', 'boardscribe' ),
				'render_cell' => [ $this, 'render_meeting_date_cell' ],
			],
			'edbs_meeting_canceled' => [
				'label'       => __( 'Canceled', 'boardscribe' ),
				'render_cell' => [ $this, 'render_canceled_cell' ],
			],
			'edbs_agenda_url'       => [
				'label'       => __( 'Agenda', 'boardscribe' ),
				'render_cell' => [ $this, 'render_agenda_cell' ],
			],
			'edbs_minutes_url'      => [
				'label'       => __( 'Minutes', 'boardscribe' ),
				'render_cell' => [ $this, 'render_minutes_cell' ],
			],
		];

		/**
		 * Filters the columns shown on the Board Meetings admin list table.
		 *
		 * Each entry is keyed by column key with `label` (string) and
		 * `render_cell` (callable `fn( int $post_id, \WP_Post $post ): string`,
		 * returning pre-escaped cell HTML - same trust contract as
		 * edbs_block_preview_columns' render_cell). Pro plugin uses this to
		 * add its own columns (location, category, linked documents).
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, array{label: string, render_cell: callable}> $columns The column definitions.
		 */
		return apply_filters( 'edbs_admin_columns', $columns );
	}

	/**
	 * Inserts the configured columns into the list table, immediately
	 * before the native "Date" (published) column.
	 *
	 * @since 1.1.0
	 *
	 * @param array $columns The existing list table columns.
	 * @return array
	 */
	public function add_columns( array $columns ): array {
		$config      = $this->get_columns_config();
		$new_columns = [];

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				foreach ( $config as $column_key => $column ) {
					$new_columns[ $column_key ] = $column['label'];
				}
			}
			$new_columns[ $key ] = $label;
		}

		// No native "date" column to anchor on (shouldn't happen for this
		// CPT, but avoids silently dropping the columns if it ever does).
		if ( ! isset( $columns['date'] ) ) {
			$new_columns = array_merge(
				$new_columns,
				wp_list_pluck( $config, 'label' )
			);
		}

		return $new_columns;
	}

	/**
	 * Renders one custom column's cell content.
	 *
	 * @since 1.1.0
	 *
	 * @param string $column  The column key being rendered.
	 * @param int    $post_id The current row's post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		$config = $this->get_columns_config();

		if ( ! isset( $config[ $column ] ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Cell markup is pre-escaped by contract (see get_columns_config()).
		echo call_user_func( $config[ $column ]['render_cell'], $post_id, $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renders the Meeting Date column cell.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function render_meeting_date_cell( int $post_id ): string {
		$meeting_date = (string) get_post_meta( $post_id, 'edbs_meeting_date', true );
		$date_object  = BoardScribeEndpoint::parse_date( $meeting_date );

		if ( ! $date_object ) {
			return '&#8212;';
		}

		$date_format = (string) get_option( 'date_format' );

		return esc_html( $date_object->format( '' !== $date_format ? $date_format : 'F j, Y' ) );
	}

	/**
	 * Renders the Canceled column cell.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function render_canceled_cell( int $post_id ): string {
		$not_held = (bool) get_post_meta( $post_id, 'edbs_meeting_not_held', true );

		if ( ! $not_held ) {
			return '&#8212;';
		}

		return '<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">'
			. esc_html__( 'Canceled', 'boardscribe' )
			. '</span>';
	}

	/**
	 * Renders the Agenda column cell.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function render_agenda_cell( int $post_id ): string {
		return $this->render_url_cell( (string) get_post_meta( $post_id, 'edbs_agenda_url', true ) );
	}

	/**
	 * Renders the Minutes column cell.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function render_minutes_cell( int $post_id ): string {
		return $this->render_url_cell( (string) get_post_meta( $post_id, 'edbs_minutes_url', true ) );
	}

	/**
	 * Builds a "View" link cell for a stored URL, or an em dash when empty.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url The raw URL meta value.
	 * @return string
	 */
	private function render_url_cell( string $url ): string {
		if ( '' === $url ) {
			return '&#8212;';
		}

		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'boardscribe' ) . '</a>';
	}

	/**
	 * Marks the Meeting Date column as sortable.
	 *
	 * @since 1.1.0
	 *
	 * @param array $columns The existing sortable columns.
	 * @return array
	 */
	public function sortable_columns( array $columns ): array {
		$columns['edbs_meeting_date'] = 'edbs_meeting_date';
		return $columns;
	}

	/**
	 * Orders the list table by the raw edbs_meeting_date meta value when
	 * sorted by the Meeting Date column.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_Query $query The current query.
	 * @return void
	 */
	public function sort_by_meeting_date( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'edbs_meeting' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'edbs_meeting_date' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', 'edbs_meeting_date' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required to sort by meeting date.
		$query->set( 'orderby', 'meta_value' );
	}
}
