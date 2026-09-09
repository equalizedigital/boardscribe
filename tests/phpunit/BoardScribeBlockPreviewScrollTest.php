<?php
/**
 * Tests for BoardScribeBlock::render_preview_table()'s horizontal-scroll wrapper.
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Block\BoardScribeBlock;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers PRO-1202: a wide preview table (many visible columns, or long
 * unbreakable cell content) must scroll horizontally within the block
 * editor's preview container instead of overflowing it.
 */
class BoardScribeBlockPreviewScrollTest extends TestCase {

	/**
	 * A single-column table wrapped in a horizontally scrollable container.
	 */
	public function test_preview_table_is_wrapped_in_a_scroll_container(): void {
		$block = new BoardScribeBlock();

		$html = $block->render_preview_table(
			[
				'title' => [
					'label'       => 'Title',
					'render_cell' => static fn(): string => 'A Meeting',
				],
			],
			[],
			[]
		);

		$this->assertMatchesRegularExpression(
			'/<div[^>]*style="[^"]*overflow-x:\s*auto[^"]*"[^>]*>\s*<table/',
			$html
		);
	}
}
