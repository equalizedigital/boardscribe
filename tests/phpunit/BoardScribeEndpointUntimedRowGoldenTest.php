<?php
/**
 * Golden-output tests for BoardScribeEndpoint::build_meeting_row() on meetings
 * that have no time.
 *
 * Meeting times are optional and additive: a meeting without one must produce
 * exactly the row it produced before times existed. These literals were captured
 * from the output before the feature and must not change.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Pins the untimed row shape.
 */
class BoardScribeEndpointUntimedRowGoldenTest extends TestCase {

	/**
	 * Format args matching the REST route's shape.
	 *
	 * @var array<string, string>
	 */
	private array $format_args = [
		'held_date_format'     => 'Y/m/d',
		'not_held_date_format' => 'Y/m',
		'agenda_link_label'    => 'View Agenda',
		'minutes_link_label'   => 'View Minutes',
	];

	/**
	 * Creates a meeting with the given meta.
	 *
	 * @param array $meta Meta key/value pairs.
	 * @return int The post ID.
	 */
	private function create_meeting( array $meta ): int {
		$post_id = self::factory()->post->create(
			[
				'post_type'  => 'edbs_meeting',
				'post_title' => 'Regular Meeting',
			]
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		return $post_id;
	}

	/**
	 * Builds the row for a meeting.
	 *
	 * @param int $post_id Meeting ID.
	 * @return array
	 */
	private function row( int $post_id ): array {
		return ( new BoardScribeEndpoint() )->build_meeting_row( $post_id, $this->format_args );
	}

	/**
	 * Held meeting with agenda and minutes.
	 */
	public function test_held_meeting_row_is_unchanged(): void {
		$id = $this->create_meeting(
			[
				'edbs_meeting_date' => '2026-03-12',
				'edbs_agenda_url'   => 'https://example.com/a.pdf',
				'edbs_minutes_url'  => 'https://example.com/m.pdf',
			]
		);

		$this->assertSame(
			[
				'title'   => 'Regular Meeting',
				'date'    => '2026/03/12',
				'agenda'  => '<a href="https://example.com/a.pdf" aria-label="View Agenda for 2026/03/12">View Agenda</a>',
				'minutes' => '<a href="https://example.com/m.pdf" aria-label="View Minutes for 2026/03/12">View Minutes</a>',
			],
			$this->row( $id )
		);
	}

	/**
	 * Held meeting with no documents.
	 */
	public function test_held_meeting_without_documents_row_is_unchanged(): void {
		$id = $this->create_meeting( [ 'edbs_meeting_date' => '2026-03-12' ] );

		$this->assertSame(
			[
				'title'   => 'Regular Meeting',
				'date'    => '2026/03/12',
				'agenda'  => '<span class="sr-text screen-reader-text">Agenda not available</span>',
				'minutes' => '<span class="sr-text screen-reader-text">Minutes not available</span>',
			],
			$this->row( $id )
		);
	}

	/**
	 * Not-held meeting.
	 */
	public function test_not_held_meeting_row_is_unchanged(): void {
		$id = $this->create_meeting(
			[
				'edbs_meeting_date'     => '2026-03-12',
				'edbs_meeting_not_held' => '1',
				'edbs_minutes_url'      => 'https://example.com/m.pdf',
			]
		);

		$this->assertSame(
			[
				'title'   => 'Regular Meeting',
				'date'    => '2026/03',
				'agenda'  => 'Meeting not held',
				'minutes' => '<a href="https://example.com/m.pdf" aria-label="View Minutes for 2026/03">View Minutes</a>',
			],
			$this->row( $id )
		);
	}

	/**
	 * Missing and unparseable dates.
	 *
	 * @dataProvider undated_meta_provider
	 *
	 * @param array $meta Meta for the meeting.
	 */
	public function test_undated_meeting_row_is_unchanged( array $meta ): void {
		$this->assertSame(
			[
				'title'   => 'Regular Meeting',
				'date'    => '<span class="sr-text screen-reader-text">Date not available</span>',
				'agenda'  => '<span class="sr-text screen-reader-text">Agenda not available</span>',
				'minutes' => '<span class="sr-text screen-reader-text">Minutes not available</span>',
			],
			$this->row( $this->create_meeting( $meta ) )
		);
	}

	/**
	 * Provides meta for undated meetings.
	 *
	 * @return array<string, array{0: array}>
	 */
	public function undated_meta_provider(): array {
		return [
			'no date meta'     => [ [] ],
			'unparseable date' => [ [ 'edbs_meeting_date' => 'garbage' ] ],
		];
	}

	/**
	 * A filtered date reaches the date cell and the link labels.
	 */
	public function test_filtered_date_row_is_unchanged(): void {
		$id = $this->create_meeting(
			[
				'edbs_meeting_date' => '2026-03-12',
				'edbs_agenda_url'   => 'https://example.com/a.pdf',
				'edbs_minutes_url'  => 'https://example.com/m.pdf',
			]
		);

		$filter = static fn() => 'TBD';
		add_filter( 'edbs_meeting_formatted_date', $filter );

		$row = $this->row( $id );

		remove_filter( 'edbs_meeting_formatted_date', $filter );

		$this->assertSame(
			[
				'title'   => 'Regular Meeting',
				'date'    => 'TBD',
				'agenda'  => '<a href="https://example.com/a.pdf" aria-label="View Agenda for TBD">View Agenda</a>',
				'minutes' => '<a href="https://example.com/m.pdf" aria-label="View Minutes for TBD">View Minutes</a>',
			],
			$row
		);
	}
}
