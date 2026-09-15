<?php
/**
 * Tests for MetaBoxFieldRegistry.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBoxFieldRegistry;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the edbs_meeting_meta_fields filter and insert_after ordering -
 * the data-driven replacement for the old per-row action hooks, and the
 * only place a plugin (Pro) controls where its own field's row lands.
 */
class MetaBoxFieldRegistryTest extends TestCase {

	/**
	 * Resets any filter added by a test.
	 */
	public function tear_down(): void {
		remove_all_filters( 'edbs_meeting_meta_fields' );
		parent::tear_down();
	}

	/**
	 * The four core fields are present, in their declared order, when
	 * nothing filters the registry.
	 */
	public function test_core_fields_are_present_in_declared_order(): void {
		$keys = wp_list_pluck( MetaBoxFieldRegistry::all(), 'key' );

		$this->assertSame(
			[ 'edbs_meeting_date', 'edbs_agenda_url', 'edbs_minutes_url', 'edbs_meeting_not_held' ],
			$keys
		);
	}

	/**
	 * A field with no insert_after is appended at the end, same as the old
	 * edbs_meta_fields hook's behavior.
	 */
	public function test_field_without_insert_after_is_appended(): void {
		add_filter(
			'edbs_meeting_meta_fields',
			static function ( array $fields ): array {
				$fields[] = [
					'key'   => 'pro_category',
					'type'  => 'text',
					'label' => 'Category',
				];
				return $fields;
			}
		);

		$keys = wp_list_pluck( MetaBoxFieldRegistry::all(), 'key' );

		$this->assertSame( 'pro_category', end( $keys ) );
	}

	/**
	 * A field with insert_after lands directly after its target row,
	 * mirroring what edbs_after_agenda_url_field used to guarantee.
	 */
	public function test_field_with_insert_after_lands_directly_after_its_target(): void {
		add_filter(
			'edbs_meeting_meta_fields',
			static function ( array $fields ): array {
				$fields[] = [
					'key'          => 'pro_agenda_document',
					'type'         => 'document_picker',
					'label'        => 'Agenda Document',
					'insert_after' => 'edbs_agenda_url',
				];
				return $fields;
			}
		);

		$keys = wp_list_pluck( MetaBoxFieldRegistry::all(), 'key' );

		$this->assertSame( 'pro_agenda_document', $keys[ array_search( 'edbs_agenda_url', $keys, true ) + 1 ] );
	}

	/**
	 * A field targeting a key that doesn't exist in the registry is
	 * appended rather than silently dropped.
	 */
	public function test_field_with_unknown_insert_after_target_is_appended_not_dropped(): void {
		add_filter(
			'edbs_meeting_meta_fields',
			static function ( array $fields ): array {
				$fields[] = [
					'key'          => 'pro_orphan_field',
					'type'         => 'text',
					'label'        => 'Orphan',
					'insert_after' => 'does_not_exist',
				];
				return $fields;
			}
		);

		$keys = wp_list_pluck( MetaBoxFieldRegistry::all(), 'key' );

		$this->assertContains( 'pro_orphan_field', $keys );
	}

	/**
	 * js_schema() drops PHP-only keys (sanitize_callback isn't JSON-safe)
	 * and always includes the JS-consumed keys, defaulting optional ones.
	 */
	public function test_js_schema_drops_sanitize_callback_and_fills_defaults(): void {
		add_filter(
			'edbs_meeting_meta_fields',
			static function ( array $fields ): array {
				$fields[] = [
					'key'               => 'pro_field',
					'type'              => 'text',
					'label'             => 'Pro Field',
					'sanitize_callback' => 'sanitize_text_field',
				];
				return $fields;
			}
		);

		$schema = MetaBoxFieldRegistry::js_schema();
		$pro    = current(
			array_filter( $schema, static fn( $field ) => 'pro_field' === $field['key'] )
		);

		$this->assertArrayNotHasKey( 'sanitize_callback', $pro );
		$this->assertFalse( $pro['required'] );
		$this->assertFalse( $pro['mediaPicker'] );
	}

	/**
	 * js_schema() also drops render_callback (not JSON-safe either) and
	 * projects init_fn to its camelCase JS key, defaulting to null when a
	 * field doesn't set one.
	 */
	public function test_js_schema_drops_render_callback_and_projects_init_fn(): void {
		add_filter(
			'edbs_meeting_meta_fields',
			static function ( array $fields ): array {
				$fields[] = [
					'key'              => 'pro_widget',
					'type'             => 'html',
					'label'            => 'Pro Widget',
					'render_callback'  => '__return_empty_string',
					'init_fn'          => 'edbsInitProWidget',
				];
				return $fields;
			}
		);

		$schema = MetaBoxFieldRegistry::js_schema();
		$pro    = current(
			array_filter( $schema, static fn( $field ) => 'pro_widget' === $field['key'] )
		);

		$this->assertArrayNotHasKey( 'render_callback', $pro );
		$this->assertSame( 'edbsInitProWidget', $pro['initFn'] );

		$core = current(
			array_filter( $schema, static fn( $field ) => 'edbs_meeting_date' === $field['key'] )
		);
		$this->assertNull( $core['initFn'] );
	}
}
