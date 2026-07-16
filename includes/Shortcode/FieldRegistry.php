<?php
/**
 * Central registry for shortcode/builder/REST/block field descriptors.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EqualizeDigital\BoardScribe\REST\BoardScribeEndpoint;

/**
 * Single source of truth for every shortcode attribute this plugin
 * recognizes. Registers the free plugin's own fields on the
 * edbs_shortcode_field_registry filter, and provides the shared,
 * type-based value resolvers that every consumer of that filter uses:
 * BoardScribeShortcode (attribute defaults + instance config),
 * BoardScribeEndpoint (REST args schema), the settings-page builder
 * UI, and Pro's Gutenberg block.
 */
class FieldRegistry {

	/**
	 * Field types this registry understands. Each has a default value
	 * resolver in resolve_value() and a default renderer in the
	 * settings-page builder partial.
	 */
	const TYPE_TEXT            = 'text';
	const TYPE_TEXTAREA        = 'textarea';
	const TYPE_CHECKBOX        = 'checkbox';
	const TYPE_SELECT          = 'select';
	const TYPE_NUMBER          = 'number';
	const TYPE_NUMBER_WITH_ALL = 'number_with_all';

	/**
	 * Hooks the free plugin's own fields into the registry filter.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'edbs_shortcode_field_registry', [ __CLASS__, 'add_core_fields' ], 1 );
	}

	/**
	 * Returns the merged field registry: the free plugin's own core
	 * fields plus anything appended by Pro (or any other plugin) via
	 * the edbs_shortcode_field_registry filter.
	 *
	 * Every call re-runs the full filter chain — there is no cached
	 * state to invalidate, matching every other single-purpose filter
	 * in this plugin.
	 *
	 * @since x.x.x
	 *
	 * @return array[] List of field descriptors, see add_core_fields().
	 */
	public static function all(): array {
		// A third-party callback returning a non-array would otherwise
		// throw a fatal TypeError against this method's array return type.
		$fields = apply_filters( 'edbs_shortcode_field_registry', [] );
		$fields = is_array( $fields ) ? $fields : [];

		// Two descriptors sharing the same key would otherwise render as
		// two separate builder-UI controls with the same name="" attribute.
		// Every other consumer (shortcode defaults, instance config, REST
		// args) already resolves duplicates to whichever was registered
		// last via plain array-key assignment - keep the builder UI
		// consistent with that same last-one-wins behavior.
		$by_key = [];
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && isset( $field['key'] ) ) {
				$by_key[ $field['key'] ] = $field;
			}
		}

		return array_values( $by_key );
	}

	/**
	 * Adds the free plugin's own field descriptors.
	 *
	 * Each descriptor is:
	 * {
	 *
	 *     @type string        $key                 Shortcode attribute / REST arg name (snake_case). Required.
	 *     @type string        $type                One of the TYPE_* constants. Required.
	 *     @type string        $group               One of: general, column_labels, hide_columns, show_columns, link_labels.
	 *     @type string        $label               Human-readable label (builder UI caption/placeholder, block InspectorControl label).
	 *     @type mixed         $default             Default shortcode-attribute value.
	 *     @type array         $choices             Value => label options. Only used when type is TYPE_SELECT.
	 *     @type callable|null $sanitize_callback   Overrides the type's default resolver, see resolve_value().
	 *     @type callable|null $validate_callback   Optional REST validate_callback, only used when rest_arg is true.
	 *     @type bool          $rest_arg            Whether this field also becomes a REST route arg.
	 *     @type string|null   $config_key          Overrides the camelCase instance-config key derived from $key.
	 *     @type string|null   $block_attribute_key Overrides the camelCase block-attribute key derived from $key/config_key.
	 * }
	 *
	 * @since x.x.x
	 *
	 * @param array[] $fields Field descriptors contributed by earlier-priority callbacks.
	 * @return array[]
	 */
	public static function add_core_fields( array $fields ): array {
		return array_merge(
			[
				[
					'key'         => 'included_years',
					'type'        => self::TYPE_TEXT,
					'group'       => 'general',
					'label'       => __( 'Included Years', 'boardscribe' ),
					'default'     => '',
					'placeholder' => '2023,2024',
					'description' => __( 'Comma-separated list of years. Leave blank to show all years.', 'boardscribe' ),
					'rest_arg'    => true,
				],
				[
					'key'      => 'posts_per_page',
					'type'     => self::TYPE_NUMBER_WITH_ALL,
					'group'    => 'general',
					'label'    => __( 'Posts Per Page', 'boardscribe' ),
					'default'  => 20,
					'rest_arg' => true,
				],
				[
					'key'               => 'held_date_format',
					'type'              => self::TYPE_TEXT,
					'group'             => 'general',
					'label'             => __( 'Held Date Format', 'boardscribe' ),
					'default'           => 'Y/m/d',
					'description'       => __( 'PHP date() format. See php.net/manual/en/datetime.format.php for the full reference.', 'boardscribe' ),
					'sanitize_callback' => static function ( $value ) {
						return self::sanitize_date_format( $value, 'Y/m/d' );
					},
					'validate_callback' => [ BoardScribeEndpoint::class, 'validate_date_format' ],
					'rest_arg'          => true,
				],
				[
					'key'               => 'not_held_date_format',
					'type'              => self::TYPE_TEXT,
					'group'             => 'general',
					'label'             => __( 'Not Held Date Format', 'boardscribe' ),
					'default'           => 'Y/m',
					'sanitize_callback' => static function ( $value ) {
						return self::sanitize_date_format( $value, 'Y/m' );
					},
					'validate_callback' => [ BoardScribeEndpoint::class, 'validate_date_format' ],
					'rest_arg'          => true,
				],
				[
					'key'     => 'template',
					'type'    => self::TYPE_SELECT,
					'group'   => 'general',
					'label'   => __( 'Display Template', 'boardscribe' ),
					'default' => '',
					// Only the built-in table ships here. A plugin providing
					// another display template (e.g. Pro's year-timeline)
					// appends its choice to this descriptor via the
					// edbs_shortcode_field_registry filter, alongside
					// registering the template itself on window.edbsTemplates.
					'choices' => [
						'' => __( 'Table (default)', 'boardscribe' ),
					],
				],
				[
					'key'         => 'equal_columns',
					'type'        => self::TYPE_CHECKBOX,
					'group'       => 'general',
					'label'       => __( 'Force all columns to the same width', 'boardscribe' ),
					'default'     => false,
					'description' => __( 'Helps align columns consistently for vertical rhythm across rows.', 'boardscribe' ),
				],
				[
					'key'                 => 'class',
					'type'                => self::TYPE_TEXT,
					'group'               => 'general',
					'label'               => __( 'Custom CSS Class', 'boardscribe' ),
					'default'             => '',
					'sanitize_callback'   => [ __CLASS__, 'sanitize_class_list' ],
					'config_key'          => 'tableClass',
					'block_attribute_key' => 'className',
				],
				[
					'key'     => 'title_label',
					'type'    => self::TYPE_TEXT,
					'group'   => 'column_labels',
					'label'   => __( 'Title', 'boardscribe' ),
					'default' => '',
				],
				[
					'key'     => 'date_label',
					'type'    => self::TYPE_TEXT,
					'group'   => 'column_labels',
					'label'   => __( 'Date', 'boardscribe' ),
					'default' => '',
				],
				[
					'key'     => 'agenda_label',
					'type'    => self::TYPE_TEXT,
					'group'   => 'column_labels',
					'label'   => __( 'Agenda', 'boardscribe' ),
					'default' => '',
				],
				[
					'key'     => 'minutes_label',
					'type'    => self::TYPE_TEXT,
					'group'   => 'column_labels',
					'label'   => __( 'Minutes', 'boardscribe' ),
					'default' => '',
				],
				[
					'key'      => 'agenda_link_label',
					'type'     => self::TYPE_TEXT,
					'group'    => 'link_labels',
					'label'    => __( 'Agenda Link Label', 'boardscribe' ),
					'default'  => '',
					'rest_arg' => true,
				],
				[
					'key'      => 'minutes_link_label',
					'type'     => self::TYPE_TEXT,
					'group'    => 'link_labels',
					'label'    => __( 'Minutes Link Label', 'boardscribe' ),
					'default'  => '',
					'rest_arg' => true,
				],
				[
					'key'     => 'hide_title',
					'type'    => self::TYPE_CHECKBOX,
					'group'   => 'hide_columns',
					'label'   => __( 'Title', 'boardscribe' ),
					'default' => false,
				],
				[
					'key'     => 'hide_date',
					'type'    => self::TYPE_CHECKBOX,
					'group'   => 'hide_columns',
					'label'   => __( 'Date', 'boardscribe' ),
					'default' => false,
				],
				[
					'key'     => 'hide_agenda',
					'type'    => self::TYPE_CHECKBOX,
					'group'   => 'hide_columns',
					'label'   => __( 'Agenda', 'boardscribe' ),
					'default' => false,
				],
				[
					'key'     => 'hide_minutes',
					'type'    => self::TYPE_CHECKBOX,
					'group'   => 'hide_columns',
					'label'   => __( 'Minutes', 'boardscribe' ),
					'default' => false,
				],
			],
			$fields
		);
	}

	/**
	 * Resolves a raw shortcode-attribute value into its sanitized
	 * instance-config value, using the field's own sanitize_callback
	 * if it has one, otherwise a built-in resolver for its type.
	 *
	 * @since x.x.x
	 *
	 * @param array $field     The field descriptor, see add_core_fields().
	 * @param mixed $raw_value The raw shortcode-attribute value.
	 * @return mixed
	 */
	public static function resolve_value( array $field, $raw_value ) {
		// A REST request can hand us an array for a param declared as a
		// plain string (e.g. a repeated query param) - casting that to
		// string below would throw an "Array to string conversion"
		// notice, so normalize it to empty string up front instead.
		if ( is_array( $raw_value ) ) {
			$raw_value = '';
		}

		if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $raw_value );
		}

		switch ( $field['type'] ) {
			case self::TYPE_CHECKBOX:
				return filter_var( $raw_value, FILTER_VALIDATE_BOOLEAN );

			case self::TYPE_NUMBER:
				return absint( $raw_value );

			case self::TYPE_NUMBER_WITH_ALL:
				return self::sanitize_number_with_all( $raw_value );

			case self::TYPE_SELECT:
				// Not restricted to $field['choices'] - that list is only
				// used to populate the builder UI's <select> options. A
				// template name Pro/third parties register purely in JS
				// (window.edbsTemplates) has no matching PHP-side choice to
				// validate against, so any well-formed key is accepted here,
				// same as the pre-registry sanitize_key() behavior.
				return sanitize_key( (string) $raw_value );

			case self::TYPE_TEXTAREA:
				return sanitize_textarea_field( (string) $raw_value );

			case self::TYPE_TEXT:
			default:
				return sanitize_text_field( (string) $raw_value );
		}
	}

	/**
	 * Derives the camelCase instance-config/block-attribute key for a
	 * field from its snake_case $key, unless the descriptor overrides
	 * it explicitly.
	 *
	 * @since x.x.x
	 *
	 * @param array $field The field descriptor.
	 * @return string
	 */
	public static function config_key( array $field ): string {
		if ( ! empty( $field['config_key'] ) ) {
			return $field['config_key'];
		}
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $field['key'] ) ) ) );
	}

	/**
	 * Derives the camelCase block-attribute key for a field. Defaults
	 * to the same value as config_key() — most fields use the same
	 * name for instance config and the block attribute — unless the
	 * descriptor overrides it explicitly (e.g. class -> className).
	 *
	 * @since x.x.x
	 *
	 * @param array $field The field descriptor.
	 * @return string
	 */
	public static function block_attribute_key( array $field ): string {
		if ( ! empty( $field['block_attribute_key'] ) ) {
			return $field['block_attribute_key'];
		}
		return self::config_key( $field );
	}

	/**
	 * Sanitizes a PHP date() format string, falling back to $default_value
	 * if it fails BoardScribeEndpoint::validate_date_format() — shared
	 * by the held_date_format and not_held_date_format field descriptors,
	 * which differ only in their fallback value.
	 *
	 * @since x.x.x
	 *
	 * @param mixed  $value         Raw date-format value.
	 * @param string $default_value Fallback value when invalid.
	 * @return string
	 */
	public static function sanitize_date_format( $value, string $default_value ): string {
		$value = sanitize_text_field( (string) $value );
		return BoardScribeEndpoint::validate_date_format( $value ) ? $value : $default_value;
	}

	/**
	 * Sanitizes a space-separated list of HTML class names.
	 *
	 * The `class` shortcode attribute is rendered client-side into the
	 * table markup's class attribute via string concatenation, so it
	 * must be restricted to safe class-name characters before it ever
	 * reaches the instance config, to prevent stored XSS via the
	 * shortcode attribute.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $class_list Raw, space-separated class names.
	 * @return string Sanitized, space-separated class names.
	 */
	public static function sanitize_class_list( $class_list ): string {
		$classes = array_map( 'sanitize_html_class', explode( ' ', (string) $class_list ) );
		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Sanitizes a posts_per_page-style value, accepting "all" (any
	 * case/whitespace) as "no limit" (-1). Any other non-positive value
	 * (0, -1, -5, non-numeric, non-scalar, etc.) also normalizes to -1
	 * rather than being passed through absint() — which would otherwise
	 * turn "0" into a literal 0 (WP_Query renders zero posts for
	 * posts_per_page=0, not "all") or flip a mistyped negative like
	 * "-5" into a positive 5. A genuine positive count is returned as-is.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value The raw posts_per_page value.
	 * @return int
	 */
	public static function sanitize_number_with_all( $value ): int {
		if ( is_string( $value ) && 'all' === strtolower( trim( $value ) ) ) {
			return -1;
		}

		if ( ! is_scalar( $value ) ) {
			return -1;
		}

		$int = (int) $value;
		return $int > 0 ? $int : -1;
	}
}
