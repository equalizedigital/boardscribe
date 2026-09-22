<?php
/**
 * Gutenberg block registration for BoardScribe.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EqualizeDigital\BoardScribe\Shortcode\BoardScribeShortcode;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;

/**
 * Registers the equalize-digital/boardscribe dynamic block.
 *
 * The block is registered from block.json so the editor script is declared
 * there. The render_callback here generates the front-end output by mapping
 * block attributes to a shortcode atts array and calling
 * BoardScribeShortcode::render() directly (see render_block()'s own
 * docblock for why not do_shortcode()).
 *
 * The block's attribute schema, the editor sidebar controls, and the
 * shortcode attributes all derive from the same shared field registry
 * (see FieldRegistry), so a Pro/third-party field registered on the
 * edbs_shortcode_field_registry filter appears in the block with no
 * changes here.
 */
class BoardScribeBlock {

	/**
	 * The registered block name. Must never change: it's stored in the
	 * block markup of every post using the block (including content
	 * created while the block still shipped in the Pro plugin).
	 */
	const BLOCK_NAME = 'equalize-digital/boardscribe';

	/**
	 * Hooks block registration into WordPress.
	 *
	 * Priority 20: Pro versions that predate the block moving into this
	 * plugin register the identical block themselves on init 10. Letting
	 * that registration land first (and skipping ours below) keeps an
	 * old-Pro/new-free combination working with no _doing_it_wrong notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ], 20 );
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_frontend_assets' ] );
	}

	/**
	 * Loads the real frontend rendering pipeline into the block editor, so
	 * edit()'s live preview (window.edbsInitInstance) has everything it
	 * needs - the same parity trick the Shortcode Builder's admin page
	 * already relies on for its own live preview (see
	 * BoardScribeShortcode::enqueue_assets()'s own docblock). Without
	 * this, edbs-boardscribe (and, via its edbs_enqueue_assets action,
	 * any Pro/third-party window.edbsTemplates/edbsExtraColumns
	 * registrations) would never load in the editor at all -
	 * enqueue_block_editor_assets doesn't fire them on its own.
	 *
	 * Also adds edbs-boardscribe as a dependency of the block's own
	 * editor script handle, so window.edbsInitInstance is guaranteed
	 * loaded before edit() runs - block.json's editorScript is
	 * registered once, at register_block() time (on init), so its own
	 * deps array has to be appended to here rather than declared
	 * up front the way build_block_attributes() declares attributes.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function enqueue_editor_frontend_assets(): void {
		( new BoardScribeShortcode() )->enqueue_assets();

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );
		if ( ! $block_type instanceof \WP_Block_Type ) {
			return;
		}

		$scripts = wp_scripts();
		foreach ( $block_type->editor_script_handles as $handle ) {
			if ( isset( $scripts->registered[ $handle ] )
				&& ! in_array( 'edbs-boardscribe', $scripts->registered[ $handle ]->deps, true )
			) {
				$scripts->registered[ $handle ]->deps[] = 'edbs-boardscribe';
			}
		}
	}

	/**
	 * Registers the "BoardScribe" editor category the block lives in.
	 *
	 * The block.json metadata references this category by its `boardscribe`
	 * slug, which does nothing until the category is inserted into the
	 * editor's list here. Guards against double-registration in case an old
	 * Pro build has already added it (see register_block() for the same
	 * old-Pro concern).
	 *
	 * @since 1.0.0
	 *
	 * @param array $categories Existing block categories.
	 * @return array The category list with the BoardScribe category appended.
	 */
	public function register_block_category( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && 'boardscribe' === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = [
			'slug'  => 'boardscribe',
			'title' => __( 'BoardScribe', 'boardscribe' ),
			'icon'  => null,
		];

		return $categories;
	}

	/**
	 * Registers the block type from block.json with a PHP render callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// See register() — an old Pro build may already have registered
		// the identical block; re-registering would only raise a notice.
		if ( \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			return;
		}

		$block_type = register_block_type(
			EDBS_DIR . 'block.json',
			[
				'attributes'      => $this->build_block_attributes(),
				'render_callback' => [ $this, 'render_block' ],
			]
		);

		// Exposes the same field registry the InspectorControls are built
		// from at edit time (src/js/block/index.js reads window.edbsBlockFieldRegistry)
		// so a field only needs to be added in one place, PHP-side, to
		// show up in the block editor sidebar.
		if ( $block_type instanceof \WP_Block_Type ) {
			foreach ( $block_type->editor_script_handles as $handle ) {
				wp_localize_script( $handle, 'edbsBlockFieldRegistry', FieldRegistry::js_schema() );
				wp_set_script_translations( $handle, 'boardscribe' );
			}
		}
	}

	/**
	 * Builds the block's attribute schema from the shared field registry
	 * (same source shortcode defaults/instance-config/REST args and the
	 * shortcode builder UI all derive from), instead of hand-maintaining
	 * a duplicate list in block.json - a `register_block_type()` args
	 * array shallow-merges over block.json's metadata, so this replaces
	 * block.json's own (absent) "attributes" key entirely.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{type: string, default: mixed}>
	 */
	private function build_block_attributes(): array {
		$type_map = [
			FieldRegistry::TYPE_TEXT            => 'string',
			FieldRegistry::TYPE_TEXTAREA        => 'string',
			FieldRegistry::TYPE_CHECKBOX        => 'boolean',
			FieldRegistry::TYPE_SELECT          => 'string',
			FieldRegistry::TYPE_NUMBER          => 'number',
			FieldRegistry::TYPE_NUMBER_WITH_ALL => 'number',
			FieldRegistry::TYPE_DATE            => 'string',
		];

		$attributes = [];
		foreach ( FieldRegistry::all() as $field ) {
			$type = $type_map[ $field['type'] ] ?? 'string';

			// A field descriptor that omits its own default shouldn't fall
			// back to an empty string for a boolean attribute - Gutenberg
			// treats that as a type mismatch (validation warnings, and the
			// control never renders "unchecked" correctly).
			$default = $field['default'] ?? ( 'boolean' === $type ? false : '' );

			$attributes[ FieldRegistry::block_attribute_key( $field ) ] = [
				'type'    => $type,
				'default' => $default,
			];
		}

		return $attributes;
	}

	/**
	 * Renders the block by mapping its attributes to shortcode attributes
	 * and calling BoardScribeShortcode::render() directly with an atts
	 * array - the same output on the front end and in the block editor.
	 * The editor's own live preview (edit(), via window.edbsInitInstance)
	 * renders independently client-side against the block's *current,
	 * unsaved* attributes; this render_callback only ever runs against the
	 * block's *saved* attributes (the front end, or the editor's read-only
	 * "preview" mode when content hasn't changed), so the two were never
	 * the same render pass to begin with - see PRO-1331.
	 *
	 * Calls render() directly rather than building a '[edbs_boardscribe
	 * ...]' string and running it through do_shortcode(): WP's shortcode
	 * regex finds an attribute value's closing quote by scanning for the
	 * next `]`, not by tracking quote state, so a value containing a
	 * literal `]` (e.g. a custom label like "Meetings [Board]") would
	 * truncate the generated shortcode string there - esc_attr() alone
	 * doesn't escape `]`. Skipping the text round-trip avoids that
	 * (and any other shortcode-attribute-parsing quirk) entirely, since
	 * render() already accepts a plain atts array. See PRO-1368.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $attributes Block attributes from the editor.
	 * @param string $content    Inner block content (unused — no inner blocks).
	 * @return string The rendered HTML.
	 */
	public function render_block( array $attributes, string $content ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- required by block render_callback signature.
		return ( new BoardScribeShortcode() )->render( $this->build_shortcode_atts( $attributes ) );
	}

	/**
	 * Maps block attributes to a shortcode atts array (key/value pairs,
	 * the same shape BoardScribeShortcode::render() accepts directly - no
	 * markup/string building here, see render_block()'s own docblock for
	 * why).
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return array<string, string> Shortcode attributes, keyed by shortcode attribute name.
	 */
	private function build_shortcode_atts( array $attributes ): array {
		$atts = [];

		foreach ( FieldRegistry::all() as $field ) {
			$block_key = FieldRegistry::block_attribute_key( $field );
			$value     = $attributes[ $block_key ] ?? '';

			if ( FieldRegistry::TYPE_CHECKBOX === $field['type'] ) {
				// filter_var(), not !empty(): a stringified "false" (as
				// could arrive from hand-edited block markup) is non-empty
				// in PHP and would otherwise be treated as checked.
				if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
					$atts[ $field['key'] ] = 'true';
				}
				continue;
			}

			if ( '' !== $value ) {
				$atts[ $field['key'] ] = (string) $value;
			}
		}

		return $atts;
	}
}
