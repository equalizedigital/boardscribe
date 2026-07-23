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

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;
use EqualizeDigital\BoardScribe\Shortcode\FieldRegistry;

/**
 * Registers the equalize-digital/boardscribe dynamic block.
 *
 * The block is registered from block.json so the editor script is declared
 * there. The render_callback here generates the front-end output by mapping
 * block attributes to shortcode attributes and calling do_shortcode().
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
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ], 20 );
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ] );
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
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * Renders the block. In the block editor (REST preview context) a
	 * server-rendered HTML table is returned so the editor shows real content.
	 * On the front end the JS-driven shortcode output is returned as normal.
	 *
	 * @since x.x.x
	 *
	 * @param array  $attributes Block attributes from the editor.
	 * @param string $content    Inner block content (unused — no inner blocks).
	 * @return string The rendered HTML.
	 */
	public function render_block( array $attributes, string $content ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- required by block render_callback signature.
		// In the editor the REST API renders the block server-side for preview.
		// The shortcode output is empty divs populated by JS, which never runs
		// in the editor preview context — so we render a real HTML preview instead.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $this->render_editor_preview( $attributes );
		}

		return do_shortcode( '[edbs_boardscribe' . $this->build_shortcode_atts( $attributes ) . ']' );
	}

	/**
	 * Builds a shortcode attribute string from block attributes.
	 *
	 * @since x.x.x
	 *
	 * @param array $attributes Block attributes.
	 * @return string Shortcode attribute string (leading space included if non-empty).
	 */
	private function build_shortcode_atts( array $attributes ): string {
		$atts = '';

		foreach ( FieldRegistry::all() as $field ) {
			$block_key = FieldRegistry::block_attribute_key( $field );
			$value     = $attributes[ $block_key ] ?? '';

			if ( FieldRegistry::TYPE_CHECKBOX === $field['type'] ) {
				// filter_var(), not !empty(): a stringified "false" (as
				// could arrive from hand-edited block markup) is non-empty
				// in PHP and would otherwise be treated as checked.
				if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
					$atts .= ' ' . $field['key'] . '="true"';
				}
				continue;
			}

			if ( '' !== $value ) {
				$atts .= ' ' . $field['key'] . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		return $atts;
	}

	/**
	 * Renders a real HTML table preview for the block editor.
	 *
	 * Queries the latest meeting posts, builds each row through
	 * BoardScribeEndpoint::build_meeting_row() — the same escaping and
	 * formatting pipeline the REST endpoint uses, including the
	 * edbs_meeting_row_data filter, so Pro row fields are present — and
	 * renders a static table over a filterable column list.
	 *
	 * @since x.x.x
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML preview.
	 */
	private function render_editor_preview( array $attributes ): string {
		/**
		 * Filters the editor preview's row cap. The preview is a static
		 * server-rendered table, so it stays capped for performance even
		 * when postsPerPage is -1 ("show all meetings") — but a template
		 * whose preview needs more rows to be representative (e.g. Pro's
		 * year-timeline, which wants more than one year group visible)
		 * can raise it.
		 *
		 * @since x.x.x
		 *
		 * @param int   $max_rows   Maximum preview rows. Default 5.
		 * @param array $attributes Block attributes.
		 */
		$max_rows = (int) apply_filters( 'edbs_block_preview_max_rows', 5, $attributes );

		// -1 ("show all meetings") requests unlimited rows from WP_Query,
		// but the editor preview stays capped regardless - PHP_INT_MAX here
		// just avoids absint() mangling -1 back down to 1 before the cap.
		$requested_per_page = $attributes['postsPerPage'] ?? 5;
		$posts_per_page     = ( -1 === (int) $requested_per_page ) ? PHP_INT_MAX : absint( $requested_per_page );
		$posts_per_page     = min( $posts_per_page, max( 1, $max_rows ) );

		$query_args = [
			'post_type'      => 'edbs_meeting',
			'posts_per_page' => $posts_per_page,
			'post_status'    => 'publish',
			'orderby'        => 'meta_value',
			'meta_key'       => 'edbs_meeting_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required for date ordering.
			'order'          => 'DESC',
			'no_found_rows'  => true,
		];

		if ( ! empty( $attributes['includedYears'] ) ) {
			$years = array_filter( array_map( 'trim', explode( ',', $attributes['includedYears'] ) ) );
			if ( $years ) {
				$query_args['date_query']             = array_map(
					static function ( string $year ): array {
						return [ 'year' => (int) $year ];
					},
					$years
				);
				$query_args['date_query']['relation'] = 'OR';
			}
		}

		$posts = get_posts( $query_args );

		$endpoint    = new BoardScribeEndpoint();
		$format_args = [
			'held_date_format'     => $attributes['heldDateFormat'] ?? 'F j, Y',
			'not_held_date_format' => $attributes['notHeldDateFormat'] ?? 'F Y',
			'agenda_link_label'    => $attributes['agendaLinkLabel'] ?? '',
			'minutes_link_label'   => $attributes['minutesLinkLabel'] ?? '',
		];

		$rows = array_map(
			static function ( \WP_Post $meeting_post ) use ( $endpoint, $format_args ): array {
				return [
					'post' => $meeting_post,
					'row'  => $endpoint->build_meeting_row( $meeting_post->ID, $format_args ),
				];
			},
			$posts
		);

		/**
		 * Filters the editor preview's column list. Each entry is keyed by
		 * column name and shaped as:
		 * {
		 *
		 *     @type string   $label       Escaped-on-output column header text.
		 *     @type bool     $hidden      Whether the column is hidden for these attributes.
		 *     @type callable $render_cell fn( array $row, \WP_Post $post ): string — returns
		 *                                 pre-escaped cell HTML (same trust contract as the
		 *                                 front end's window.edbsExtraColumns renderCell()).
		 *                                 $row is the build_meeting_row() output, so fields
		 *                                 added via edbs_meeting_row_data are available.
		 * }
		 *
		 * @since x.x.x
		 *
		 * @param array $columns    Column definitions, see above.
		 * @param array $attributes Block attributes.
		 */
		$columns = apply_filters( 'edbs_block_preview_columns', $this->core_preview_columns( $attributes ), $attributes );

		/**
		 * Short-circuits the editor preview with template-specific markup.
		 * Returning a string uses it as the full preview HTML; anything
		 * else falls through to the default flat table below. The plugin
		 * that owns a display template hooks here (e.g. Pro's year-timeline
		 * renders one table per year via render_preview_table()).
		 *
		 * @since x.x.x
		 *
		 * @param string|null        $preview    The preview HTML, or null to use the default.
		 * @param array              $attributes Block attributes.
		 * @param array              $rows       List of { post: \WP_Post, row: array } entries.
		 * @param array              $columns    Filtered column definitions, see edbs_block_preview_columns.
		 * @param BoardScribeBlock $block     This instance, for render_preview_table().
		 */
		$preview = apply_filters( 'edbs_block_editor_preview', null, $attributes, $rows, $columns, $this );
		if ( is_string( $preview ) ) {
			return $preview;
		}

		return $this->render_preview_table( $columns, $rows, $attributes );
	}

	/**
	 * Renders one preview table for a set of rows and column definitions.
	 *
	 * Public so an edbs_block_editor_preview callback rendering multiple
	 * sections (e.g. one table per year) can call it per section instead
	 * of re-implementing the table markup — the PHP analogue of the front
	 * end's window.edbsBuildTable().
	 *
	 * @since x.x.x
	 *
	 * @param array $columns    Column definitions, see the edbs_block_preview_columns filter.
	 * @param array $rows       List of { post: \WP_Post, row: array } entries.
	 * @param array $attributes Block attributes.
	 * @return string The table HTML.
	 */
	public function render_preview_table( array $columns, array $rows, array $attributes ): string {
		// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- consumed by the required partials/block-editor-preview.php, which the sniff can't see across the file boundary.
		$visible_columns = array_filter(
			$columns,
			static function ( $column ): bool {
				return is_array( $column ) && empty( $column['hidden'] ) && is_callable( $column['render_cell'] ?? null );
			}
		);

		$equal_columns = ! empty( $attributes['equalColumns'] );
		// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		ob_start();
		require EDBS_DIR . 'partials/block-editor-preview.php';
		return ob_get_clean();
	}

	/**
	 * Builds the preview column definitions for the four core columns,
	 * mirroring the front-end table template's label resolution and
	 * hide-toggle handling (src/js/templates/table.js).
	 *
	 * @since x.x.x
	 *
	 * @param array $attributes Block attributes.
	 * @return array Column definitions, see the edbs_block_preview_columns filter.
	 */
	private function core_preview_columns( array $attributes ): array {
		$label = static function ( string $key, string $default_label ) use ( $attributes ): string {
			$value = (string) ( $attributes[ $key ] ?? '' );
			return '' !== $value ? $value : $default_label;
		};

		$row_field = static function ( string $key ): callable {
			return static function ( array $row, \WP_Post $meeting_post ) use ( $key ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- fixed render_cell signature.
				// build_meeting_row() values are pre-escaped HTML by contract.
				return (string) ( $row[ $key ] ?? '' );
			};
		};

		return [
			'title'   => [
				'label'       => $label( 'titleLabel', __( 'Title', 'boardscribe' ) ),
				'hidden'      => ! empty( $attributes['hideTitle'] ),
				'render_cell' => $row_field( 'title' ),
			],
			'date'    => [
				'label'       => $label( 'dateLabel', __( 'Date', 'boardscribe' ) ),
				'hidden'      => ! empty( $attributes['hideDate'] ),
				'render_cell' => $row_field( 'date' ),
			],
			'agenda'  => [
				'label'       => $label( 'agendaLabel', __( 'Agenda', 'boardscribe' ) ),
				'hidden'      => ! empty( $attributes['hideAgenda'] ),
				'render_cell' => $row_field( 'agenda' ),
			],
			'minutes' => [
				'label'       => $label( 'minutesLabel', __( 'Minutes', 'boardscribe' ) ),
				'hidden'      => ! empty( $attributes['hideMinutes'] ),
				'render_cell' => $row_field( 'minutes' ),
			],
		];
	}
}
