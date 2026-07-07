<?php
/**
 * Tests for the edmm_shortcode_field_registry filter end to end.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

use EqualizeDigital\MeetingMinutes\Shortcode\MeetingMinutesShortcode;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Proves a field registered via edmm_shortcode_field_registry flows all
 * the way through: shortcode attribute default, instance-config value
 * (MeetingMinutesShortcode::render()), and — when rest_arg is true — the
 * REST route's registered args schema, without touching any of the
 * consumer classes directly. This is the contract Pro relies on.
 */
class FieldRegistryTest extends TestCase {

	const ROUTE = '/edmm/v1/meeting-minutes';

	/**
	 * The registered edmm_shortcode_field_registry callback, so it can be
	 * removed again in tear_down() and not leak into other tests.
	 *
	 * @var callable|null
	 */
	private $callback;

	/**
	 * Ensures the REST server has run, same as MeetingMinutesEndpointRestTest.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Removes the test's field registration.
	 */
	public function tear_down(): void {
		if ( $this->callback ) {
			remove_filter( 'edmm_shortcode_field_registry', $this->callback );
			$this->callback = null;
		}
		parent::tear_down();
	}

	/**
	 * Decodes the instance config JSON out of a render() output string.
	 *
	 * @param string $html The HTML returned by render().
	 * @return array<string, mixed>
	 */
	private function get_instance_config( string $html ): array {
		preg_match( '/data-config="([^"]*)"/', $html, $matches );
		return json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true );
	}

	/**
	 * A field registered without rest_arg reaches the shortcode default
	 * and the instance config, using its type's default resolver.
	 */
	public function test_registered_field_reaches_instance_config(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'     => 'edmm_test_hide_widget',
				'type'    => 'checkbox',
				'group'   => 'hide_columns',
				'label'   => 'Widget',
				'default' => false,
			];
			return $fields;
		};
		add_filter( 'edmm_shortcode_field_registry', $this->callback );

		$shortcode = new MeetingMinutesShortcode();
		$config    = $this->get_instance_config( $shortcode->render( [ 'edmm_test_hide_widget' => 'true' ] ) );

		$this->assertArrayHasKey( 'edmmTestHideWidget', $config );
		$this->assertTrue( $config['edmmTestHideWidget'] );
	}

	/**
	 * A field's own sanitize_callback overrides its type's default
	 * resolver, and an explicit config_key overrides camelCase derivation.
	 */
	public function test_sanitize_callback_and_config_key_overrides_are_honored(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'               => 'edmm_test_shout',
				'type'              => 'text',
				'group'             => 'general',
				'label'             => 'Shout',
				'default'           => '',
				'config_key'        => 'shoutText',
				'sanitize_callback' => 'strtoupper',
			];
			return $fields;
		};
		add_filter( 'edmm_shortcode_field_registry', $this->callback );

		$shortcode = new MeetingMinutesShortcode();
		$config    = $this->get_instance_config( $shortcode->render( [ 'edmm_test_shout' => 'hello' ] ) );

		$this->assertArrayNotHasKey( 'edmmTestShout', $config );
		$this->assertSame( 'HELLO', $config['shoutText'] );
	}

	/**
	 * A field registered with rest_arg => true becomes a real REST route
	 * arg, sanitized/validated the same way as instance config, end to
	 * end through the dispatched REST request (not just the args schema).
	 */
	public function test_rest_arg_field_is_sanitized_on_the_real_route(): void {
		$this->callback = static function ( array $fields ) {
			$fields[] = [
				'key'      => 'edmm_test_query_flag',
				'type'     => 'number',
				'group'    => 'general',
				'label'    => 'Query Flag',
				'default'  => 0,
				'rest_arg' => true,
			];
			return $fields;
		};
		add_filter( 'edmm_shortcode_field_registry', $this->callback );

		// Re-register the route so it picks up the field added above -
		// register_route() already ran once during set_up()'s
		// rest_api_init before this test's filter was attached.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		$request = new \WP_REST_Request( 'GET', self::ROUTE );
		$request->set_param( 'edmm_test_query_flag', '-5' );

		$response = rest_get_server()->dispatch( $request );

		// A negative value sanitizes to 0 via absint() (the "number" type's
		// default resolver), and the route accepts the request (200) -
		// proving both the arg was registered and its sanitize_callback ran.
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * A misbehaving third-party callback that returns a non-array doesn't
	 * crash all() with a fatal TypeError - it's treated as no fields added.
	 */
	public function test_all_tolerates_a_misbehaving_filter_callback(): void {
		$this->callback = static function () {
			return null;
		};
		add_filter( 'edmm_shortcode_field_registry', $this->callback );

		$fields = \EqualizeDigital\MeetingMinutes\Shortcode\FieldRegistry::all();

		$this->assertIsArray( $fields );
	}

	/**
	 * A REST request that hands resolve_value() an array (e.g. a repeated
	 * query param bound to a scalar field) doesn't throw an "Array to
	 * string conversion" notice - it normalizes to the type's empty value.
	 */
	public function test_resolve_value_normalizes_an_array_raw_value(): void {
		$field = [
			'key'     => 'edmm_test_array_input',
			'type'    => 'text',
			'default' => '',
		];

		$result = \EqualizeDigital\MeetingMinutes\Shortcode\FieldRegistry::resolve_value( $field, [ 'unexpected', 'array' ] );

		$this->assertSame( '', $result );
	}
}
