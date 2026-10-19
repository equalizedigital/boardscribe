<?php
/**
 * Tests for BoardScribeBlock::render_block() (PRO-1368).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Block\BoardScribeBlock;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * render_block() used to build a '[edbs_boardscribe ...]' string (each
 * attribute value escaped only via esc_attr()) and hand it to
 * do_shortcode(). WP's shortcode regex finds an attribute value's closing
 * quote by scanning for the next `]`, not by tracking quote state -
 * esc_attr() doesn't escape `]` - so a block attribute containing a
 * literal `]` (e.g. a custom label like "Meetings [Board]") truncated the
 * generated shortcode string there, silently dropping the rest of the
 * attributes. render_block() now calls BoardScribeShortcode::render()
 * directly with a plain atts array, skipping the string round-trip (and
 * the whole class of shortcode-text-parsing edge cases) entirely.
 */
class BoardScribeBlockRenderTest extends TestCase {

	/**
	 * Renders the block and decodes its data-config JSON attribute.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Decoded instance config.
	 */
	private function render_config( array $attributes ): array {
		$block  = new BoardScribeBlock();
		$html   = $block->render_block( $attributes, '' );
		$config = [];

		if ( preg_match( '/data-config="([^"]*)"/', $html, $matches ) ) {
			$config = json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true ) ?? [];
		}

		return $config;
	}

	/**
	 * A block attribute value containing a literal `]` survives intact,
	 * instead of truncating every attribute that followed it in the old
	 * do_shortcode()-based implementation.
	 */
	public function test_attribute_value_containing_a_closing_bracket_is_not_truncated(): void {
		$config = $this->render_config(
			[
				'titleLabel' => 'Meetings [Board]',
				'dateLabel'  => 'Meeting Date',
			]
		);

		$this->assertSame( 'Meetings [Board]', $config['titleLabel'] ?? null );
		// The old bug didn't just corrupt titleLabel's own value - the `]`
		// inside it truncated the *generated shortcode string*, so every
		// attribute after it (dateLabel here) was silently dropped too.
		$this->assertSame( 'Meeting Date', $config['dateLabel'] ?? null );
	}

	/**
	 * A checkbox-type attribute set to a stringified "false" (as could
	 * arrive from hand-edited block markup) still resolves to unchecked,
	 * confirming the array-based atts path preserves this existing
	 * filter_var()-based handling.
	 */
	public function test_checkbox_attribute_stringified_false_is_not_treated_as_checked(): void {
		$config = $this->render_config( [ 'hideTitle' => 'false' ] );

		$this->assertFalse( $config['hideTitle'] ?? null );
	}

	/**
	 * Explicit false must override a checked-by-default extension field,
	 * while an omitted attribute must retain the registry default.
	 *
	 * @dataProvider checked_by_default_attribute_provider
	 * @param array $attributes Block attributes.
	 * @param bool  $expected   Expected visibility setting.
	 */
	public function test_checked_by_default_field_preserves_explicit_values( array $attributes, bool $expected ): void {
		$register_field = static function ( array $fields ): array {
			$fields[] = [
				'key'     => 'hide_test_column',
				'type'    => FieldRegistry::TYPE_CHECKBOX,
				'group'   => 'hide_columns',
				'label'   => 'Test column',
				'default' => true,
			];
			return $fields;
		};
		add_filter( 'edbs_shortcode_field_registry', $register_field );

		try {
			$config = $this->render_config( $attributes );
			$this->assertSame( $expected, $config['hideTestColumn'] ?? null );
		} finally {
			remove_filter( 'edbs_shortcode_field_registry', $register_field );
		}
	}

	/**
	 * Values supplied by the editor or hand-edited block markup.
	 *
	 * @return array<string, array{array, bool}>
	 */
	public function checked_by_default_attribute_provider(): array {
		return [
			'omitted uses default' => [ [], true ],
			'checked'              => [ [ 'hideTestColumn' => true ], true ],
			'unchecked'            => [ [ 'hideTestColumn' => false ], false ],
			'string false'         => [ [ 'hideTestColumn' => 'false' ], false ],
		];
	}
}
