<?php
/**
 * Tests for BoardScribeShortcode::render().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the shortcode's public render() contract - in particular the
 * class-attribute sanitization (stored XSS fix) and the date-format
 * validation/fallback (fix for the shortcode silently breaking the
 * whole table when given an invalid held_date_format/not_held_date_format).
 *
 * sanitize_class_list()/sanitize_date_format() are private, so these
 * tests go through the public render() output rather than reflection -
 * that keeps the tests tied to the actual observable contract.
 */
class BoardScribeShortcodeTest extends TestCase {

	/**
	 * The shortcode instance under test.
	 *
	 * @var BoardScribeShortcode
	 */
	private BoardScribeShortcode $shortcode;

	/**
	 * Sets up a fresh shortcode instance for each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->shortcode = new BoardScribeShortcode();
	}

	/**
	 * Decodes the instance config JSON out of a render() output string.
	 *
	 * @param string $html The HTML returned by render().
	 * @return array<string, mixed>
	 */
	private function get_instance_config( string $html ): array {
		$matched = preg_match( '/data-config="([^"]*)"/', $html, $matches );
		$this->assertSame( 1, $matched, 'The data-config attribute was not found in the HTML.' );

		$json   = html_entity_decode( $matches[1], ENT_QUOTES );
		$config = json_decode( $json, true );
		$this->assertIsArray( $config );

		return $config;
	}

	/**
	 * A `class` attribute containing an attribute-breakout attempt is
	 * reduced to safe class-name characters only.
	 *
	 * Note: sanitize_html_class() strips per-token to safe class-name
	 * characters, but harmless-looking words like "onmouseover" remain
	 * as a (meaningless but inert) class name - what actually matters is
	 * that no attribute-breakout characters (", <, >, =, (, )) survive,
	 * since those are what would let the value escape the class="..."
	 * attribute context it's rendered into client-side.
	 */
	public function test_class_attribute_is_sanitized(): void {
		$html   = $this->shortcode->render( [ 'class' => 'foo" onmouseover="alert(1)' ] );
		$config = $this->get_instance_config( $html );

		foreach ( [ '"', '<', '>', '=', '(', ')' ] as $dangerous_char ) {
			$this->assertStringNotContainsString( $dangerous_char, $config['tableClass'] );
		}
	}

	/**
	 * A plain, valid class list passes through unchanged.
	 */
	public function test_valid_class_attribute_is_preserved(): void {
		$html   = $this->shortcode->render( [ 'class' => 'my-custom-class another-class' ] );
		$config = $this->get_instance_config( $html );

		$this->assertSame( 'my-custom-class another-class', $config['tableClass'] );
	}

	/**
	 * An invalid held_date_format falls back to the default rather than
	 * reaching the REST call and breaking the whole table.
	 */
	public function test_invalid_held_date_format_falls_back_to_default(): void {
		$html   = $this->shortcode->render( [ 'held_date_format' => '\\<\\s\\c\\r\\i\\p\\t\\>' ] );
		$config = $this->get_instance_config( $html );

		$this->assertSame( 'F j, Y', $config['heldDateFormat'] );
	}

	/**
	 * An invalid not_held_date_format falls back to its default too.
	 */
	public function test_invalid_not_held_date_format_falls_back_to_default(): void {
		$html   = $this->shortcode->render( [ 'not_held_date_format' => '<script>' ] );
		$config = $this->get_instance_config( $html );

		$this->assertSame( 'F Y', $config['notHeldDateFormat'] );
	}

	/**
	 * A valid custom date format is preserved rather than replaced.
	 */
	public function test_valid_date_format_is_preserved(): void {
		$html   = $this->shortcode->render( [ 'held_date_format' => 'F j, Y' ] );
		$config = $this->get_instance_config( $html );

		$this->assertSame( 'F j, Y', $config['heldDateFormat'] );
	}

	/**
	 * With no template attribute, the config carries an empty template
	 * name, which the JS resolves to the built-in table template.
	 */
	public function test_template_defaults_to_empty(): void {
		$config = $this->get_instance_config( $this->shortcode->render( [] ) );

		$this->assertSame( '', $config['template'] );
	}

	/**
	 * The template attribute is reduced to a sanitized key, so markup or
	 * attribute-breakout characters can't reach the data-config JSON.
	 */
	public function test_template_attribute_is_sanitized_to_a_key(): void {
		$html   = $this->shortcode->render( [ 'template' => 'Card-Grid"><script>' ] );
		$config = $this->get_instance_config( $html );

		$this->assertSame( 'card-gridscript', $config['template'] );
	}

	/**
	 * A well-formed template name passes through unchanged.
	 */
	public function test_valid_template_attribute_is_preserved(): void {
		$html   = $this->shortcode->render( [ 'template' => 'accordion' ] );
		$config = $this->get_instance_config( $html );

		$this->assertSame( 'accordion', $config['template'] );
	}

	/**
	 * Each shortcode invocation gets a unique instance ID, so multiple
	 * instances on the same page don't collide.
	 */
	public function test_multiple_instances_get_unique_ids(): void {
		$first_config  = $this->get_instance_config( $this->shortcode->render( [] ) );
		$second_config = $this->get_instance_config( $this->shortcode->render( [] ) );

		$this->assertNotSame( $first_config['instanceId'], $second_config['instanceId'] );
	}
}
