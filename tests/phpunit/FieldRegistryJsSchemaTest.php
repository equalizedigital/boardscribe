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

	/**
	 * A hidden_from_ui field (e.g. a Pro field left behind once a license
	 * lapses) is omitted from the default js_schema() call - the settings-
	 * page builder app's own use, which only ever builds a *new* shortcode
	 * and so should never offer a picker for it.
	 */
	public function test_hidden_field_is_omitted_by_default(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'            => 'edbs_test_hidden_field',
				'type'           => 'text',
				'group'          => 'general',
				'label'          => 'Hidden Field',
				'default'        => '',
				'hidden_from_ui' => true,
			];
			return $fields;
		};
		add_filter( 'edbs_shortcode_field_registry', $this->callback );

		$by_key = array_column( FieldRegistry::js_schema(), null, 'key' );

		$this->assertArrayNotHasKey( 'edbs_test_hidden_field', $by_key );
	}

	/**
	 * PRO-1397: js_schema( true ) (the block editor's own call) keeps a
	 * hidden_from_ui field in the array instead of omitting it, flagged via
	 * hiddenFromUi - so the block's live preview can still map its saved
	 * value into the instance config the same way a visible field's is,
	 * while the InspectorControls loop uses the flag to skip rendering its
	 * picker. A visible field is flagged false, not simply absent, so JS
	 * can rely on the key always being present.
	 */
	public function test_include_hidden_flags_but_keeps_hidden_field(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'            => 'edbs_test_hidden_field',
				'type'           => 'text',
				'group'          => 'general',
				'label'          => 'Hidden Field',
				'default'        => 'secret',
				'hidden_from_ui' => true,
			];
			return $fields;
		};
		add_filter( 'edbs_shortcode_field_registry', $this->callback );

		$by_key = array_column( FieldRegistry::js_schema( true ), null, 'key' );

		$this->assertArrayHasKey( 'edbs_test_hidden_field', $by_key );
		$this->assertTrue( $by_key['edbs_test_hidden_field']['hiddenFromUi'] );
		$this->assertSame( 'secret', $by_key['edbs_test_hidden_field']['default'] );
		$this->assertFalse( $by_key['posts_per_page']['hiddenFromUi'] );
	}
}
