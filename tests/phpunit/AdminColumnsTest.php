<?php
/**
 * Tests for AdminColumns.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\AdminColumns;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the Board Meetings admin list table columns: column
 * registration/ordering, per-column cell rendering (including the
 * pre-escaped-output contract shared with edbs_block_preview_columns),
 * the edbs_admin_columns extension point, and Meeting Date sortability.
 */
class AdminColumnsTest extends TestCase {

	/**
	 * The AdminColumns instance under test.
	 *
	 * @var AdminColumns
	 */
	private AdminColumns $admin_columns;

	/**
	 * Sets up a fresh AdminColumns instance for each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_columns = new AdminColumns();
	}

	/**
	 * The four default columns are inserted immediately before the
	 * native "date" (published date) column, leaving other native
	 * columns untouched.
	 */
	public function test_add_columns_inserts_before_date_column(): void {
		$columns = $this->admin_columns->add_columns(
			[
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			]
		);

		$this->assertSame(
			[ 'cb', 'title', 'edbs_meeting_date', 'edbs_meeting_canceled', 'edbs_agenda_url', 'edbs_minutes_url', 'date' ],
			array_keys( $columns )
		);
	}

	/**
	 * When there is no native "date" column to anchor on, the configured
	 * columns are appended rather than silently dropped.
	 */
	public function test_add_columns_appends_when_no_date_column_present(): void {
		$columns = $this->admin_columns->add_columns(
			[
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
			]
		);

		$this->assertSame(
			[ 'cb', 'title', 'edbs_meeting_date', 'edbs_meeting_canceled', 'edbs_agenda_url', 'edbs_minutes_url' ],
			array_keys( $columns )
		);
	}

	/**
	 * The Meeting Date cell renders the site's date_format for a valid
	 * stored date, and an em dash when no date is set.
	 */
	public function test_render_meeting_date_cell(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $post_id, 'edbs_meeting_date', '2024-03-15' );

		$date_object = DateTime::createFromFormat( 'Y-m-d', '2024-03-15' );
		$expected    = esc_html( $date_object->format( get_option( 'date_format' ) ) );

		$this->assertSame( $expected, $this->admin_columns->render_meeting_date_cell( $post_id ) );

		$empty_post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$this->assertSame( '&#8212;', $this->admin_columns->render_meeting_date_cell( $empty_post_id ) );
	}

	/**
	 * The Canceled cell shows a dashicon (with screen-reader text) when
	 * the meeting is marked not held, and an em dash otherwise.
	 */
	public function test_render_canceled_cell(): void {
		$canceled_post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $canceled_post_id, 'edbs_meeting_not_held', '1' );

		$this->assertStringContainsString( 'dashicons-yes', $this->admin_columns->render_canceled_cell( $canceled_post_id ) );

		$held_post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$this->assertSame( '&#8212;', $this->admin_columns->render_canceled_cell( $held_post_id ) );
	}

	/**
	 * The Agenda/Minutes cells link to the stored URL, escaped, and fall
	 * back to an em dash when empty.
	 */
	public function test_render_agenda_and_minutes_cells(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $post_id, 'edbs_agenda_url', 'https://example.com/agenda.pdf' );
		update_post_meta( $post_id, 'edbs_minutes_url', 'https://example.com/minutes.pdf' );

		$this->assertSame(
			'<a href="' . esc_url( 'https://example.com/agenda.pdf' ) . '" target="_blank" rel="noopener noreferrer">View</a>',
			$this->admin_columns->render_agenda_cell( $post_id )
		);
		$this->assertSame(
			'<a href="' . esc_url( 'https://example.com/minutes.pdf' ) . '" target="_blank" rel="noopener noreferrer">View</a>',
			$this->admin_columns->render_minutes_cell( $post_id )
		);

		$empty_post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$this->assertSame( '&#8212;', $this->admin_columns->render_agenda_cell( $empty_post_id ) );
		$this->assertSame( '&#8212;', $this->admin_columns->render_minutes_cell( $empty_post_id ) );
	}

	/**
	 * render_column() dispatches to the matching column's render_cell
	 * callback and echoes its (pre-escaped) output.
	 */
	public function test_render_column_echoes_matching_cell(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $post_id, 'edbs_meeting_not_held', '1' );

		$this->expectOutputRegex( '/dashicons-yes/' );
		$this->admin_columns->render_column( 'edbs_meeting_canceled', $post_id );
	}

	/**
	 * render_column() is a no-op for a column key it doesn't own.
	 */
	public function test_render_column_ignores_unknown_column(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		$this->expectOutputString( '' );
		$this->admin_columns->render_column( 'title', $post_id );
	}

	/**
	 * The Meeting Date column is marked sortable.
	 */
	public function test_sortable_columns_adds_meeting_date(): void {
		$columns = $this->admin_columns->sortable_columns( [] );

		$this->assertSame( [ 'edbs_meeting_date' => 'edbs_meeting_date' ], $columns );
	}

	/**
	 * Runs $callback with $query installed as the actual global WordPress
	 * main query, restoring the previous global afterward.
	 *
	 * WP_Query::is_main_query() compares the instance against the global
	 * $wp_the_query by identity - assigning an is_main_query property has
	 * no effect on it, so tests that need is_main_query() to return true
	 * must swap the global itself.
	 *
	 * @param \WP_Query $query    The query to install as the main query.
	 * @param callable  $callback Receives no arguments; runs with the global installed.
	 * @return void
	 */
	private function as_main_query( \WP_Query $query, callable $callback ): void {
		$previous_wp_the_query   = $GLOBALS['wp_the_query'] ?? null;
		$GLOBALS['wp_the_query'] = $query;

		try {
			$callback();
		} finally {
			$GLOBALS['wp_the_query'] = $previous_wp_the_query;
		}
	}

	/**
	 * Sorting a main edbs_meeting admin query by "edbs_meeting_date"
	 * rewrites it to order by an EXISTS/NOT EXISTS meta_query rather than
	 * a plain meta_key, so undated meetings aren't inner-joined out.
	 */
	public function test_sort_by_meeting_date_rewrites_matching_query(): void {
		set_current_screen( 'edit-edbs_meeting' );

		$query = new WP_Query();
		$query->set( 'post_type', 'edbs_meeting' );
		$query->set( 'orderby', 'edbs_meeting_date' );
		$query->set( 'order', 'ASC' );

		$this->as_main_query(
			$query,
			function () use ( $query ) {
				$this->admin_columns->sort_by_meeting_date( $query );
			}
		);

		$meta_query = $query->get( 'meta_query' );
		$this->assertSame( 'OR', $meta_query['relation'] );
		$this->assertSame( 'edbs_meeting_date', $meta_query['meeting_date_clause']['key'] );
		$this->assertSame( 'EXISTS', $meta_query['meeting_date_clause']['compare'] );
		$this->assertSame( 'edbs_meeting_date', $meta_query[0]['key'] );
		$this->assertSame( 'NOT EXISTS', $meta_query[0]['compare'] );
		$this->assertSame( [ 'meeting_date_clause' => 'ASC' ], $query->get( 'orderby' ) );
	}

	/**
	 * A query for a different post type, or a different orderby, is left
	 * untouched.
	 */
	public function test_sort_by_meeting_date_ignores_unrelated_query(): void {
		set_current_screen( 'edit-edbs_meeting' );

		$query = new WP_Query();
		$query->set( 'post_type', 'post' );
		$query->set( 'orderby', 'edbs_meeting_date' );

		$this->as_main_query(
			$query,
			function () use ( $query ) {
				$this->admin_columns->sort_by_meeting_date( $query );
			}
		);

		$this->assertSame( '', $query->get( 'meta_query' ) );
		$this->assertSame( 'edbs_meeting_date', $query->get( 'orderby' ) );
	}

	/**
	 * Regression coverage for the INNER JOIN pitfall: a meeting with no
	 * edbs_meeting_date meta at all must still appear in the admin list
	 * when it's sorted by Meeting Date, not just when it's unsorted.
	 */
	public function test_sort_by_meeting_date_keeps_undated_meetings(): void {
		set_current_screen( 'edit-edbs_meeting' );

		$dated_id   = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $dated_id, 'edbs_meeting_date', '2024-03-15' );
		$undated_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		$query = new WP_Query();

		$this->as_main_query(
			$query,
			function () use ( $query ) {
				$query->query(
					[
						'post_type' => 'edbs_meeting',
						'orderby'   => 'edbs_meeting_date',
						'order'     => 'DESC',
						'fields'    => 'ids',
					]
				);
			}
		);

		$this->assertContains( $dated_id, $query->posts );
		$this->assertContains( $undated_id, $query->posts );
	}

	/**
	 * The edbs_admin_columns filter can add/override the column set that
	 * add_columns() and render_column() both work from.
	 */
	public function test_edbs_admin_columns_filter_can_add_a_column(): void {
		$callback = static function ( array $columns ): array {
			$columns['edbs_pro_location'] = [
				'label'       => 'Location',
				'render_cell' => static fn(): string => 'Somewhere',
			];
			return $columns;
		};
		add_filter( 'edbs_admin_columns', $callback );

		$columns = $this->admin_columns->add_columns(
			[
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			]
		);

		remove_filter( 'edbs_admin_columns', $callback );

		$this->assertArrayHasKey( 'edbs_pro_location', $columns );
		$this->assertSame( 'Location', $columns['edbs_pro_location'] );
	}
}
