<?php
/**
 * Tests for the MeetingTime helper and its meta registration.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use EqualizeDigital\BoardScribe\Helpers\MeetingTime;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers normalization, formatting, markup and the "absent means absent" rule.
 */
class MeetingTimeTest extends TestCase {

	/**
	 * Re-registers meta so the REST schema and prune hooks are in place.
	 */
	public function set_up(): void {
		parent::set_up();
		( new MetaBox() )->register_post_meta();
	}

	/**
	 * Creates a meeting post.
	 *
	 * @return int
	 */
	private function create_meeting(): int {
		return self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
	}

	/**
	 * Provides raw values and their normalized result.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public function normalize_provider(): array {
		return [
			'plain'              => [ '17:00', '17:00' ],
			'midnight'           => [ '00:00', '00:00' ],
			'last minute'        => [ '23:59', '23:59' ],
			'seconds dropped'    => [ '17:00:30', '17:00' ],
			'padded whitespace'  => [ ' 09:05 ', '09:05' ],
			'unpadded hour'      => [ '5:00', '' ],
			'hour 24'            => [ '24:00', '' ],
			'minute 60'          => [ '17:60', '' ],
			'12 hour text'       => [ '5:00 PM', '' ],
			'word'               => [ 'abc', '' ],
			'empty'              => [ '', '' ],
			'array'              => [ [ '17:00' ], '' ],
			'null'               => [ null, '' ],
			'integer'            => [ 1700, '' ],
		];
	}

	/**
	 * Normalizes valid and invalid values.
	 *
	 * @dataProvider normalize_provider
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $expected Normalized value.
	 */
	public function test_normalize( $value, string $expected ): void {
		$this->assertSame( $expected, MeetingTime::normalize( $value ) );
	}

	/**
	 * The sanitize callback strips tags and rejects non-times.
	 */
	public function test_sanitize(): void {
		$this->assertSame( '17:00', MeetingTime::sanitize( '17:00' ) );
		$this->assertSame( '', MeetingTime::sanitize( '<b>17:00</b>x' ) );
		$this->assertSame( '', MeetingTime::sanitize( [ '17:00' ] ) );
	}

	/**
	 * Provides formats and whether they are valid.
	 *
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function format_provider(): array {
		return [
			'default 12 hour'   => [ 'g:i a', true ],
			'24 hour'           => [ 'H:i', true ],
			'padded 12 hour'    => [ 'h:i A', true ],
			'dotted'            => [ 'H.i', true ],
			'timezone letter T' => [ 'g:i a T', false ],
			'timezone letter e' => [ 'g:i a e', false ],
			'backslash escape'  => [ 'H \h i', false ],
			'no hour'           => [ 'i:s', false ],
			'date letters'      => [ 'l, F j, Y', false ],
			'too long'          => [ str_repeat( 'g', 33 ), false ],
			'empty'             => [ '', false ],
			'array'             => [ [ 'g:i a' ], false ],
		];
	}

	/**
	 * Validates formats.
	 *
	 * @dataProvider format_provider
	 *
	 * @param mixed $format   Candidate format.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_is_valid_format( $format, bool $expected ): void {
		$this->assertSame( $expected, MeetingTime::is_valid_format( $format ) );
	}

	/**
	 * An invalid format falls back to the default.
	 */
	public function test_sanitize_format_falls_back(): void {
		$this->assertSame( 'H:i', MeetingTime::sanitize_format( 'H:i', 'g:i a' ) );
		$this->assertSame( 'g:i a', MeetingTime::sanitize_format( 'H \h i', 'g:i a' ) );
	}

	/**
	 * Formats times, including midnight and noon, without depending on today.
	 */
	public function test_format(): void {
		$this->assertSame( '5:00 pm', MeetingTime::format( '17:00', 'g:i a' ) );
		$this->assertSame( '12:00 am', MeetingTime::format( '00:00', 'g:i a' ) );
		$this->assertSame( '12:00 pm', MeetingTime::format( '12:00', 'g:i a' ) );
		$this->assertSame( '17:05', MeetingTime::format( '17:05', 'H:i' ) );
		$this->assertSame( '', MeetingTime::format( 'abc', 'g:i a' ) );
	}

	/**
	 * Builds markup for each start/end combination.
	 */
	public function test_markup(): void {
		$this->assertSame(
			'<time datetime="17:00">5:00 pm</time>',
			MeetingTime::markup( '17:00', '', 'g:i a' )
		);
		$this->assertSame(
			'<time datetime="17:00">5:00 pm</time> to <time datetime="19:00">7:00 pm</time>',
			MeetingTime::markup( '17:00', '19:00', 'g:i a' )
		);
		$this->assertSame(
			'Ends <time datetime="19:00">7:00 pm</time>',
			MeetingTime::markup( '', '19:00', 'g:i a' )
		);
		$this->assertSame( '', MeetingTime::markup( '', '', 'g:i a' ) );
		$this->assertSame( '', MeetingTime::markup( 'abc', 'def', 'g:i a' ) );
	}

	/**
	 * End before start is shown as entered.
	 */
	public function test_markup_keeps_end_before_start(): void {
		$this->assertSame(
			'<time datetime="19:00">7:00 pm</time> to <time datetime="17:00">5:00 pm</time>',
			MeetingTime::markup( '19:00', '17:00', 'g:i a' )
		);
	}

	/**
	 * The allowlist keeps <time datetime> whatever the WordPress version's
	 * own post list does (WP 6.2 strips <time>), and still strips scripts.
	 */
	public function test_allowed_html_keeps_the_time_element(): void {
		// Drop <time> from the post list the way WP 6.2 has it, so this
		// passes only because allowed_html() adds it.
		$without_time = static function ( array $tags ): array {
			unset( $tags['time'] );
			return $tags;
		};
		add_filter( 'wp_kses_allowed_html', $without_time );

		$markup = MeetingTime::markup( '17:00', '19:00', 'g:i a' );

		$this->assertSame( $markup, wp_kses( $markup, MeetingTime::allowed_html() ) );
		$this->assertSame( [ 'datetime' => true ], MeetingTime::allowed_html()['time'] );

		remove_filter( 'wp_kses_allowed_html', $without_time );

		$this->assertSame( 'alert(1)', wp_kses( '<script>alert(1)</script>', MeetingTime::allowed_html() ) );
		$this->assertArrayHasKey( 'strong', MeetingTime::allowed_html() );
	}

	/**
	 * An attribute a site allows on <time> through wp_kses_allowed_html is kept,
	 * not replaced by datetime alone.
	 */
	public function test_allowed_html_keeps_attributes_a_site_added_to_time(): void {
		$with_class = static function ( array $tags, string $context ): array {
			if ( 'post' === $context ) {
				$tags['time'] = [ 'class' => true ];
			}
			return $tags;
		};
		add_filter( 'wp_kses_allowed_html', $with_class, 10, 2 );

		$allowed = MeetingTime::allowed_html();

		remove_filter( 'wp_kses_allowed_html', $with_class, 10 );

		$this->assertSame(
			'<time class="x" datetime="17:00">5:00 pm</time>',
			wp_kses( '<time class="x" datetime="17:00">5:00 pm</time>', $allowed )
		);
	}

	/**
	 * Both keys are registered as single strings that REST only accepts as a time.
	 */
	public function test_meta_keys_are_registered(): void {
		global $wp_meta_keys;

		foreach ( [ MeetingTime::META_START, MeetingTime::META_END ] as $key ) {
			$args = $wp_meta_keys['post']['edbs_meeting'][ $key ];
			$this->assertTrue( $args['single'] );
			$this->assertSame( MeetingTime::REST_PATTERN, $args['show_in_rest']['schema']['pattern'] );
			$this->assertSame( [ MeetingTime::class, 'sanitize' ], $args['sanitize_callback'] );
		}
	}

	/**
	 * A valid time is stored normalized; junk stores nothing at all.
	 */
	public function test_writes_store_normalized_or_nothing(): void {
		$post_id = $this->create_meeting();

		update_post_meta( $post_id, MeetingTime::META_START, '17:00:30' );
		$this->assertSame( '17:00', get_post_meta( $post_id, MeetingTime::META_START, true ) );

		update_post_meta( $post_id, MeetingTime::META_START, 'not a time' );
		$this->assertFalse( metadata_exists( 'post', $post_id, MeetingTime::META_START ), 'Junk must delete the row, not store an empty one.' );

		add_post_meta( $post_id, MeetingTime::META_END, '' );
		$this->assertFalse( metadata_exists( 'post', $post_id, MeetingTime::META_END ) );
	}

	/**
	 * Midnight is a real time, not an empty one.
	 */
	public function test_midnight_is_kept(): void {
		$post_id = $this->create_meeting();

		update_post_meta( $post_id, MeetingTime::META_START, '00:00' );

		$this->assertTrue( metadata_exists( 'post', $post_id, MeetingTime::META_START ) );
		$this->assertSame( [ 'start' => '00:00', 'end' => '' ], MeetingTime::get( $post_id ) );
	}

	/**
	 * Other meta keys are not touched by the pruning hook.
	 */
	public function test_other_meta_is_not_pruned(): void {
		$post_id = $this->create_meeting();

		update_post_meta( $post_id, 'edbs_meeting_not_held', '' );

		$this->assertTrue( metadata_exists( 'post', $post_id, 'edbs_meeting_not_held' ) );
	}

	/**
	 * A meeting with no time meta reads back as no time.
	 */
	public function test_get_without_meta(): void {
		$this->assertSame( [ 'start' => '', 'end' => '' ], MeetingTime::get( $this->create_meeting() ) );
	}
	/**
	 * Provides human-written times and what they parse to.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public function parse_provider(): array {
		return [
			'24 hour'                  => [ '17:00', '17:00' ],
			'24 hour with seconds'     => [ '17:00:30', '17:00' ],
			'last valid second'        => [ '17:00:59', '17:00' ],
			'second 60'                => [ '17:00:60', '' ],
			'second 99'                => [ '17:00:99', '' ],
			'second 99 with marker'    => [ '5:00:99 PM', '' ],
			'single digit hour'        => [ '9:05', '09:05' ],
			'midnight'                 => [ '00:00', '00:00' ],
			'12 hour pm'               => [ '5:00 PM', '17:00' ],
			'hour only pm'             => [ '5pm', '17:00' ],
			'dotted marker'            => [ '5:00p.m.', '17:00' ],
			'spaced dotted marker'     => [ '5:30 p.m.', '17:30' ],
			'single letter marker'     => [ '5p', '17:00' ],
			'midnight as 12am'         => [ '12am', '00:00' ],
			'12:30 am'                 => [ '12:30 AM', '00:30' ],
			'noon as 12pm'             => [ '12pm', '12:00' ],
			'am morning'               => [ '9:15 am', '09:15' ],
			'surrounding whitespace'   => [ '  5:00 PM  ', '17:00' ],
			'narrow no-break space'   => [ "5:00\u{202F}PM", '17:00' ],
			'no-break space'          => [ "5:00\u{00A0}PM", '17:00' ],
			'no-break space padding'  => [ "\u{00A0}5:00 PM\u{00A0}", '17:00' ],
			'24 hour with marker'      => [ '17:00 pm', '' ],
			'hour 13 pm'               => [ '13pm', '' ],
			'hour 0 pm'                => [ '0pm', '' ],
			'bare hour'                => [ '17', '' ],
			'hour 24'                  => [ '24:00', '' ],
			'minute 60'                => [ '12:60pm', '' ],
			'excel decimal'            => [ '0.708', '' ],
			'european dotted'          => [ '17.30', '' ],
			'word'                     => [ 'abc', '' ],
			'noon word'                => [ 'noon', '' ],
			'empty'                    => [ '', '' ],
			'array'                    => [ [ '17:00' ], '' ],
		];
	}

	/**
	 * Parses a time as a person writes it.
	 *
	 * @dataProvider parse_provider
	 *
	 * @param mixed  $value    Raw cell.
	 * @param string $expected Normalized HH:MM, or ''.
	 */
	public function test_parse( $value, string $expected ): void {
		$this->assertSame( $expected, MeetingTime::parse( $value ) );
	}
}
