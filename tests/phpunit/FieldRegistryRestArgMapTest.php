<?php
/**
 * Tests for FieldRegistry::rest_arg_map().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Proves the {key, configKey} map that drives the front-end request
 * builder (src/js/defaults/request.js) and the block editor preview's
 * fake REST request (BoardScribeBlock::render_editor_preview()) only
 * carries rest_arg fields, with their derived configKey resolved so
 * neither JS consumer has to know the snake_case-to-camelCase rule.
 */
class FieldRegistryRestArgMapTest extends TestCase {

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
	 * Every core rest_arg field appears, keyed correctly, and a non-
	 * rest_arg core field (e.g. `class`) is excluded.
	 */
	public function test_includes_only_rest_arg_core_fields(): void {
		$map    = FieldRegistry::rest_arg_map();
		$by_key = array_column( $map, 'configKey', 'key' );

		$this->assertSame( 'includedYears', $by_key['included_years'] );
		$this->assertSame( 'postsPerPage', $by_key['posts_per_page'] );
		$this->assertArrayNotHasKey( 'class', $by_key );
		$this->assertArrayNotHasKey( 'equal_columns', $by_key );
	}

	/**
	 * A Pro/third-party field registered via the filter with rest_arg
	 * true appears in the map with its derived configKey - the mechanism
	 * that lets request.js and the block preview forward it with no
	 * changes to either file.
	 */
	public function test_filtered_rest_arg_field_appears_with_derived_config_key(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'      => 'order',
				'type'     => 'select',
				'group'    => 'general',
				'label'    => 'Sort order',
				'default'  => 'desc',
				'rest_arg' => true,
			];
			return $fields;
		};
		add_filter( 'edbs_shortcode_field_registry', $this->callback );

		$by_key = array_column( FieldRegistry::rest_arg_map(), 'configKey', 'key' );

		$this->assertArrayHasKey( 'order', $by_key );
		$this->assertSame( 'order', $by_key['order'] );
	}

	/**
	 * A filtered field that omits rest_arg (or sets it falsy) is excluded,
	 * same as a core field with no rest_arg key.
	 */
	public function test_filtered_field_without_rest_arg_is_excluded(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'     => 'edbs_test_non_rest_field',
				'type'    => 'text',
				'group'   => 'general',
				'label'   => 'Not a REST arg',
				'default' => '',
			];
			return $fields;
		};
		add_filter( 'edbs_shortcode_field_registry', $this->callback );

		$by_key = array_column( FieldRegistry::rest_arg_map(), 'configKey', 'key' );

		$this->assertArrayNotHasKey( 'edbs_test_non_rest_field', $by_key );
	}

	/**
	 * The map is JSON-encodable, since it's localized to the front-end
	 * script via wp_localize_script() in BoardScribeShortcode.
	 */
	public function test_map_is_json_encodable(): void {
		$this->assertNotFalse( wp_json_encode( FieldRegistry::rest_arg_map() ) );
	}
}
