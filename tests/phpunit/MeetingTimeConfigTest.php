<?php
/**
 * Tests that the hide_time and time_format settings reach a table's instance
 * config from a shortcode and from a block.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Block\BoardScribeBlock;
use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Each table carries its own time settings, so two tables can differ.
 */
class MeetingTimeConfigTest extends TestCase {

	/**
	 * Decodes the data-config JSON out of rendered table markup.
	 *
	 * @param string $html Rendered markup.
	 * @return array
	 */
	private function config_from( string $html ): array {
		$this->assertSame( 1, preg_match( '/data-config="([^"]*)"/', $html, $matches ), 'No data-config attribute rendered.' );

		return json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true );
	}

	/**
	 * Renders a shortcode with the given attributes.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array Instance config.
	 */
	private function shortcode_config( array $atts ): array {
		return $this->config_from( ( new BoardScribeShortcode() )->render( $atts ) );
	}

	/**
	 * Renders a block with the given attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Instance config.
	 */
	private function block_config( array $attributes ): array {
		return $this->config_from( ( new BoardScribeBlock() )->render_block( $attributes, '' ) );
	}

	/**
	 * A table with neither setting shows the time in the default format.
	 */
	public function test_defaults(): void {
		foreach ( [ $this->shortcode_config( [] ), $this->block_config( [] ) ] as $config ) {
			$this->assertFalse( $config['hideTime'] );
			$this->assertSame( 'g:i a', $config['timeFormat'] );
		}
	}

	/**
	 * The shortcode attributes reach the config.
	 */
	public function test_shortcode_attributes_reach_the_config(): void {
		$config = $this->shortcode_config(
			[
				'hide_time'   => 'true',
				'time_format' => 'H:i',
			]
		);

		$this->assertTrue( $config['hideTime'] );
		$this->assertSame( 'H:i', $config['timeFormat'] );
	}

	/**
	 * The block attributes reach the config.
	 */
	public function test_block_attributes_reach_the_config(): void {
		$config = $this->block_config(
			[
				'hideTime'   => true,
				'timeFormat' => 'H:i',
			]
		);

		$this->assertTrue( $config['hideTime'] );
		$this->assertSame( 'H:i', $config['timeFormat'] );
	}

	/**
	 * A format that would print the server timezone falls back to the default.
	 */
	public function test_unsafe_time_format_falls_back_to_the_default(): void {
		$this->assertSame( 'g:i a', $this->shortcode_config( [ 'time_format' => 'H:i T' ] )['timeFormat'] );
		$this->assertSame( 'g:i a', $this->block_config( [ 'timeFormat' => 'H:i T' ] )['timeFormat'] );
	}
}
