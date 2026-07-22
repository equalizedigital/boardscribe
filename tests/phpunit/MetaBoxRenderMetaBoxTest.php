<?php
/**
 * Tests for MetaBox::render_meta_box().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the edbs_after_agenda_url_field / edbs_after_minutes_url_field
 * hooks added to partials/meta-box.php so a plugin can render a field
 * directly under the Agenda URL / Minutes URL row it's tightly coupled
 * to, instead of only at the end of the box via edbs_meta_fields.
 */
class MetaBoxRenderMetaBoxTest extends TestCase {

	/**
	 * Both new hooks fire, each receiving the post being edited.
	 */
	public function test_both_field_anchor_hooks_fire_with_the_post(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$post    = get_post( $post_id );

		$agenda_hook_post  = null;
		$minutes_hook_post = null;

		add_action(
			'edbs_after_agenda_url_field',
			function ( \WP_Post $hook_post ) use ( &$agenda_hook_post ) {
				$agenda_hook_post = $hook_post;
			}
		);
		add_action(
			'edbs_after_minutes_url_field',
			function ( \WP_Post $hook_post ) use ( &$minutes_hook_post ) {
				$minutes_hook_post = $hook_post;
			}
		);

		ob_start();
		( new MetaBox() )->render_meta_box( $post );
		ob_end_clean();

		$this->assertSame( $post_id, $agenda_hook_post->ID ?? null );
		$this->assertSame( $post_id, $minutes_hook_post->ID ?? null );
	}

	/**
	 * The hooks fire in field order (agenda row, then minutes row) - a
	 * plugin anchoring content to one field can rely on this to reason
	 * about output order without inspecting raw HTML.
	 */
	public function test_field_anchor_hooks_fire_in_row_order(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$post    = get_post( $post_id );

		$order = [];

		add_action(
			'edbs_after_agenda_url_field',
			function () use ( &$order ) {
				$order[] = 'agenda';
			}
		);
		add_action(
			'edbs_after_minutes_url_field',
			function () use ( &$order ) {
				$order[] = 'minutes';
			}
		);

		ob_start();
		( new MetaBox() )->render_meta_box( $post );
		ob_end_clean();

		$this->assertSame( [ 'agenda', 'minutes' ], $order );
	}

	/**
	 * Output added on edbs_after_agenda_url_field actually lands in the
	 * rendered HTML between the two field rows, not merely fired.
	 */
	public function test_agenda_hook_output_lands_between_the_two_url_fields(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$post    = get_post( $post_id );

		$marker   = 'test-agenda-anchor-marker';
		$callback = static function () use ( $marker ) {
			echo esc_html( $marker );
		};
		add_action( 'edbs_after_agenda_url_field', $callback );

		ob_start();
		( new MetaBox() )->render_meta_box( $post );
		$html = ob_get_clean();

		remove_action( 'edbs_after_agenda_url_field', $callback );

		$agenda_pos  = strpos( $html, 'edbs_agenda_url' );
		$marker_pos  = strpos( $html, $marker );
		$minutes_pos = strpos( $html, 'edbs_minutes_url' );

		$this->assertNotFalse( $marker_pos );
		$this->assertGreaterThan( $agenda_pos, $marker_pos );
		$this->assertLessThan( $minutes_pos, $marker_pos );
	}
}
