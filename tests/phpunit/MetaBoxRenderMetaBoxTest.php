<?php
/**
 * Tests for MetaBox::render_meta_box().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the React app's mount point (fields themselves are rendered
 * client-side from MetaBoxFieldRegistry::js_schema(), not by this method)
 * and the edbs_before_meta_box_fields hook, the one PHP-rendered extension
 * point left above the app.
 */
class MetaBoxRenderMetaBoxTest extends TestCase {

	/**
	 * The mount point div is present, keyed with the current post's saved
	 * meta values so the React app can hydrate without a round-trip.
	 */
	public function test_mount_point_carries_post_meta_as_data_values(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $post_id, 'edbs_meeting_date', '2024-03-15' );
		update_post_meta( $post_id, 'edbs_agenda_url', 'https://example.com/agenda.pdf' );

		ob_start();
		( new MetaBox() )->render_meta_box( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="edbs-meeting-meta-box-root"', $html );
		$this->assertStringContainsString( '2024-03-15', $html );
		$this->assertStringContainsString( 'https://example.com/agenda.pdf', $html );
	}

	/**
	 * edbs_before_meta_box_fields fires with the post being edited, before
	 * the mount point in the rendered HTML.
	 */
	public function test_before_fields_hook_fires_with_the_post_before_the_mount_point(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		$post    = get_post( $post_id );

		$marker           = 'test-before-fields-marker';
		$hook_post        = null;
		$callback         = static function ( \WP_Post $hp ) use ( &$hook_post, $marker ): void {
			$hook_post = $hp;
			echo esc_html( $marker );
		};
		add_action( 'edbs_before_meta_box_fields', $callback );

		ob_start();
		( new MetaBox() )->render_meta_box( $post );
		$html = ob_get_clean();

		remove_action( 'edbs_before_meta_box_fields', $callback );

		$this->assertSame( $post_id, $hook_post->ID ?? null );
		$this->assertLessThan( strpos( $html, 'edbs-meeting-meta-box-root' ), strpos( $html, $marker ) );
	}

	/**
	 * A field with a render_callback gets its data-values entry from that
	 * callback (called with the post being edited) instead of a plain
	 * get_post_meta() lookup on the field's own key — used for a field
	 * whose value is server-rendered markup, not a stored meta value.
	 */
	public function test_render_callback_overrides_the_default_meta_lookup(): void {
		$post_id = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );
		update_post_meta( $post_id, 'pro_widget', 'raw-meta-value-should-not-appear' );

		$callback = static function ( array $fields ): array {
			$fields[] = [
				'key'             => 'pro_widget',
				'type'            => 'html',
				'label'           => 'Pro Widget',
				'render_callback' => static fn( \WP_Post $post ): string => '<p>widget for post ' . $post->ID . '</p>',
			];
			return $fields;
		};
		add_filter( 'edbs_meeting_meta_fields', $callback );

		ob_start();
		( new MetaBox() )->render_meta_box( get_post( $post_id ) );
		$html = ob_get_clean();

		remove_filter( 'edbs_meeting_meta_fields', $callback );

		$this->assertStringContainsString( 'widget for post ' . $post_id, $html );
		$this->assertStringNotContainsString( 'raw-meta-value-should-not-appear', $html );
	}
}
