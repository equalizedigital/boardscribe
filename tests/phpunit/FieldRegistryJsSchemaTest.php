<?php
/**
 * Tests for FieldRegistry::js_schema().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Proves the JSON-safe registry projection both JS consumers rely on:
 * the block editor script reads attributeKey, the shortcode builder app
 * reads key/configKey/default - and PHP-only callables never leak in.
 */
class FieldRegistryJsSchemaTest extends TestCase {

	/**
	 * The registered edbs_shortcode_field_registry callback, so it can be
	 * removed again in tear_down() and not leak into other tests.
	 *
	 * @var callable|null
	 */
	private $callback;

	/**
	 * Removes the test's field registration.
	 */
	public function tear_down(): void {
		if ( $this->callback ) {
			remove_filter( 'edbs_shortcode_field_registry', $this->callback );
			$this->callback = null;
		}
		parent::tear_down();
	}

	/**
	 * Every schema entry carries the keys both JS consumers need, with
	 * the derived names resolved (class -> className/tableClass), and is
	 * JSON-encodable (no closures/callables from the descriptors leak in).
	 */
	public function test_schema_shape_and_derived_keys(): void {
		$schema = FieldRegistry::js_schema();

		$this->assertNotEmpty( $schema );
		$this->assertNotFalse( wp_json_encode( $schema ) );

		$by_key = array_column( $schema, null, 'key' );
		foreach ( [ 'key', 'attributeKey', 'configKey', 'type', 'group', 'label', 'default' ] as $required ) {
			$this->assertArrayHasKey( $required, $by_key['posts_per_page'] );
		}

		// The one core field whose derived names differ from each other.
		$this->assertSame( 'className', $by_key['class']['attributeKey'] );
		$this->assertSame( 'tableClass', $by_key['class']['configKey'] );
		$this->assertSame( 20, $by_key['posts_per_page']['default'] );
	}

	/**
	 * A field registered via the filter (the Pro path) appears in the
	 * schema with its sanitize_callback stripped.
	 */
	public function test_filtered_field_appears_without_callables(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'               => 'edbs_test_schema_field',
				'type'              => 'text',
				'group'             => 'general',
				'label'             => 'Schema Field',
				'default'           => 'abc',
				'sanitize_callback' => 'strtoupper',
			];
			return $fields;
		};
		add_filter( 'edbs_shortcode_field_registry', $this->callback );

		$by_key = array_column( FieldRegistry::js_schema(), null, 'key' );

		$this->assertArrayHasKey( 'edbs_test_schema_field', $by_key );
		$this->assertSame( 'edbsTestSchemaField', $by_key['edbs_test_schema_field']['configKey'] );
		$this->assertSame( 'abc', $by_key['edbs_test_schema_field']['default'] );
		$this->assertArrayNotHasKey( 'sanitize_callback', $by_key['edbs_test_schema_field'] );
	}

	/**
	 * Regression test for the block editor's InspectorControls template
	 * picker being hidden entirely when Pro isn't installed.
	 *
	 * The block editor script (src/js/block/index.js) decides whether to
	 * render the "Display Template" SelectControl purely from this
	 * schema's `template` entry - it renders whenever the field is
	 * present with at least one choice, matching the shortcode builder
	 * app's generic behavior. Free ships only the built-in "Table
	 * (default)" choice, so this proves that one-entry case still gives
	 * the block script everything it needs to show the control, without
	 * requiring Pro's filter to add a second choice.
	 */
	public function test_template_field_has_at_least_one_choice_without_pro(): void {
		$by_key = array_column( FieldRegistry::js_schema(), null, 'key' );

		$this->assertArrayHasKey( 'template', $by_key );
		$this->assertSame( 'template', $by_key['template']['attributeKey'] );
		$this->assertIsArray( $by_key['template']['choices'] );
		$this->assertNotEmpty( $by_key['template']['choices'], 'The template field must always expose at least the built-in "Table (default)" choice, so the block sidebar picker is never suppressed for lack of choices.' );
		$this->assertArrayHasKey( '', $by_key['template']['choices'] );
	}
}
