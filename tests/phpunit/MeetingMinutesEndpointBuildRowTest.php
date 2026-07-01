<?php
/**
 * Tests for MeetingMinutesEndpoint::build_meeting_row().
 *
 * @package EqualizeDigital\MeetingMinutes
 */

use EqualizeDigital\MeetingMinutes\REST\MeetingMinutesEndpoint;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the per-row output builder that was extracted for Pro plugin
 * reuse (CSV/PDF export, iCal feed, widgets) - in particular the output
 * escaping fixed after a stored-XSS finding where post titles were
 * returned unescaped in the public REST response.
 */
class MeetingMinutesEndpointBuildRowTest extends TestCase {

	/**
	 * The endpoint instance under test.
	 *
	 * @var MeetingMinutesEndpoint
	 */
	private MeetingMinutesEndpoint $endpoint;

	/**
	 * Default format args matching the REST route's defaults.
	 *
	 * @var array<string, string>
	 */
	private array $default_format_args = [
		'held_date_format'     => 'Y/m/d',
		'not_held_date_format' => 'Y/m',
		'agenda_link_label'    => 'View Agenda',
		'minutes_link_label'   => 'View Minutes',
	];

	/**
	 * Sets up a fresh endpoint instance for each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->endpoint = new MeetingMinutesEndpoint();
	}

	/**
	 * Creates a meeting minutes post with the given meta.
	 *
	 * @param array $meta Meta key/value pairs to set on the post.
	 * @param array $post_args Additional wp_insert_post args (e.g. post_title).
	 * @return int The created post ID.
	 */
	private function create_meeting( array $meta = [], array $post_args = [] ): int {
		$post_id = self::factory()->post->create(
			array_merge(
				[ 'post_type' => 'edmm_meeting_minutes' ],
				$post_args
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * A post title containing markup is escaped in the output row, not
	 * passed through raw - this is the fix for the stored XSS finding.
	 *
	 * Uses an administrator (has unfiltered_html on non-multisite) to
	 * create the post, matching the real-world scenario: only a user
	 * with unfiltered_html can get raw markup into post_title in the
	 * first place, which is why this was exploitable by Editors too.
	 */
	public function test_title_is_escaped(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$post_id = $this->create_meeting(
			[ 'edmm_meeting_date' => '2024-03-15' ],
			[ 'post_title' => '<script>alert(1)</script>' ]
		);

		wp_set_current_user( 0 );

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertStringNotContainsString( '<script>', $row['title'] );
		$this->assertStringContainsString( '&lt;script&gt;', $row['title'] );
	}

	/**
	 * A held meeting's date is formatted using held_date_format.
	 */
	public function test_held_meeting_date_uses_held_format(): void {
		$post_id = $this->create_meeting( [ 'edmm_meeting_date' => '2024-03-15' ] );

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertSame( '2024/03/15', $row['date'] );
	}

	/**
	 * A not-held meeting's date is formatted using not_held_date_format.
	 */
	public function test_not_held_meeting_date_uses_not_held_format(): void {
		$post_id = $this->create_meeting(
			[
				'edmm_meeting_date'     => '2024-03-15',
				'edmm_meeting_not_held' => '1',
			]
		);

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertSame( '2024/03', $row['date'] );
	}

	/**
	 * A missing/unparseable date falls back to the "not available" markup.
	 */
	public function test_missing_date_falls_back_to_not_available(): void {
		$post_id = $this->create_meeting();

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertStringContainsString( 'Date not available', $row['date'] );
	}

	/**
	 * A meeting not held reports "Meeting not held" as its agenda text,
	 * regardless of whether an agenda URL is set.
	 */
	public function test_not_held_meeting_agenda_text(): void {
		$post_id = $this->create_meeting(
			[
				'edmm_meeting_date'        => '2024-03-15',
				'edmm_meeting_not_held'    => '1',
				'edmm_meeting_agenda_url'  => 'https://example.com/agenda.pdf',
			]
		);

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertSame( 'Meeting not held', $row['agenda'] );
	}

	/**
	 * A held meeting with no agenda URL gets the "not available" markup.
	 */
	public function test_missing_agenda_url_falls_back_to_not_available(): void {
		$post_id = $this->create_meeting( [ 'edmm_meeting_date' => '2024-03-15' ] );

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertStringContainsString( 'Agenda not available', $row['agenda'] );
	}

	/**
	 * A held meeting with an agenda URL gets a link built from it.
	 */
	public function test_agenda_url_produces_a_link(): void {
		$post_id = $this->create_meeting(
			[
				'edmm_meeting_date'       => '2024-03-15',
				'edmm_meeting_agenda_url' => 'https://example.com/agenda.pdf',
			]
		);

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertStringContainsString( 'href="https://example.com/agenda.pdf"', $row['agenda'] );
		$this->assertStringContainsString( 'View Agenda', $row['agenda'] );
	}

	/**
	 * A held meeting with no minutes URL gets the "not available" markup.
	 *
	 * Mirrors test_missing_agenda_url_falls_back_to_not_available() -
	 * the agenda/minutes code paths are nearly identical, so a
	 * copy-paste bug in one wouldn't be caught without covering both.
	 */
	public function test_missing_minutes_url_falls_back_to_not_available(): void {
		$post_id = $this->create_meeting( [ 'edmm_meeting_date' => '2024-03-15' ] );

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertStringContainsString( 'Minutes not available', $row['minutes'] );
	}

	/**
	 * A held meeting with a minutes URL gets a link built from it.
	 *
	 * Mirrors test_agenda_url_produces_a_link().
	 */
	public function test_minutes_url_produces_a_link(): void {
		$post_id = $this->create_meeting(
			[
				'edmm_meeting_date'        => '2024-03-15',
				'edmm_meeting_minutes_url' => 'https://example.com/minutes.pdf',
			]
		);

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		$this->assertStringContainsString( 'href="https://example.com/minutes.pdf"', $row['minutes'] );
		$this->assertStringContainsString( 'View Minutes', $row['minutes'] );
	}

	/**
	 * Calling build_meeting_row() with no $request (e.g. from a future
	 * non-REST caller like a CSV export) does not throw.
	 */
	public function test_works_without_a_request_object(): void {
		$post_id = $this->create_meeting( [ 'edmm_meeting_date' => '2024-03-15' ] );

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args, null );

		$this->assertSame( '2024/03/15', $row['date'] );
	}

	/**
	 * The edmm_meeting_row_data filter can add/override fields on the row.
	 */
	public function test_row_data_filter_is_applied(): void {
		$post_id = $this->create_meeting( [ 'edmm_meeting_date' => '2024-03-15' ] );

		$callback = static function ( array $row ): array {
			$row['custom_field'] = 'custom_value';
			return $row;
		};
		add_filter( 'edmm_meeting_row_data', $callback );

		$row = $this->endpoint->build_meeting_row( $post_id, $this->default_format_args );

		remove_filter( 'edmm_meeting_row_data', $callback );

		$this->assertSame( 'custom_value', $row['custom_field'] );
	}
}
