<?php
/**
 * Single source of truth for the Meeting Details meta box's fields.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of fields rendered by the React Meeting Details meta box app
 * (src/js/metabox/) and saved by MetaBox::save_meta(). Replaces the old
 * per-row do_action() hooks (edbs_after_agenda_url_field,
 * edbs_after_minutes_url_field, edbs_meta_fields) — a field's row position
 * is now data (insert_after), not a hardcoded PHP template hook, since the
 * whole table is React-rendered. See docs/HOOK-CONTRACT-CHANGES.md.
 */
class MetaBoxFieldRegistry {

	/**
	 * Returns the merged, ordered field list (core fields + anything a
	 * plugin adds via edbs_meeting_meta_fields).
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		/**
		 * Filters the Meeting Details meta box's field list. Each field is:
		 *
		 *     @type string        $key               Post meta key, and the form field's name/id — also the
		 *                                             $_POST key MetaBox::save_meta() reads, unless
		 *                                             $saved_externally is set.
		 *     @type string        $type              'date'|'url'|'checkbox'|'text'|'textarea'|'resource'|
		 *                                             'resource_list'|'html', or a custom type a plugin's JS
		 *                                             registers a control for via window.edbsMetaBoxControls
		 *                                             (see src/js/metabox/index.js). 'resource' is a single
		 *                                             URL-shaped value (same $_POST/meta shape as 'url')
		 *                                             displayed as a card — filled shows title/chips/meta +
		 *                                             View/Replace/Remove, empty shows a dashed "Add {label}"
		 *                                             prompt; Replace/Add opens a modal offering one or more
		 *                                             $sources. Every 'resource' field automatically gets a
		 *                                             sibling `{key}_source` meta (registered/saved/rendered
		 *                                             generically by MetaBox — no registry entry of its own)
		 *                                             recording which source produced the current value, so
		 *                                             the card's chip doesn't have to guess from the URL's
		 *                                             shape (see MetaBox::save_resource_source() and the free
		 *                                             repo's resource-utils.js resolveResourceDisplay()).
		 *                                             'resource_list' is a reorderable (drag handle) repeater of
		 *                                             `{ label, url, source }` items, each its own card with an
		 *                                             editable title — its $_POST shape is `{key}[i][label]`/
		 *                                             `[url]`/`[source]`, always $saved_externally (see
		 *                                             $item_noun below); `source` is that row's own
		 *                                             `{key}_source` equivalent, and the plugin owning the
		 *                                             field's save handling must persist it alongside label/url
		 *                                             (see Pro's ProMetaFields::save_label_url_pairs_field()).
		 *                                             'html' renders pre-rendered server markup as-is (see
		 *                                             $render_callback/$init_fn below) — the lighter-weight
		 *                                             option when a plugin already has working PHP + plain JS
		 *                                             for a self-contained widget (own inputs, own state) and
		 *                                             doesn't want to reimplement it as React.
		 *     @type string        $label              Field label.
		 *     @type string|null   $group              Optional section heading. Fields render in one flat
		 *                                             list by default (no heading, same as the meta box's
		 *                                             own native title covering all of them); when a field's
		 *                                             $group differs from the field immediately before it in
		 *                                             final render order, the React app inserts a heading row
		 *                                             for the new group before it. A group's fields don't need
		 *                                             to be contiguous in the filter's return order — only in
		 *                                             *final* order, after $insert_after is resolved (see
		 *                                             Pro's ProMetaFields::add_meta_box_fields() for the
		 *                                             pattern of extracting an already-registered core field
		 *                                             and re-adding it later, with both a new $insert_after
		 *                                             and a new $group, to relocate it into a different
		 *                                             plugin's section).
		 *     @type string|null   $description        Optional help text under the field.
		 *     @type bool          $required           Optional, default false.
		 *     @type string|null   $placeholder         Optional input placeholder ('text'/'url'/'textarea').
		 *     @type bool          $media_picker        Optional, 'url' type only. Adds a "Media Library" button.
		 *     @type string|null   $media_title         Optional. wp.media() modal title ('url' type's button, or
		 *                                              'resource' type's media_library source).
		 *     @type array|null    $sources             Optional, 'resource' type only. Source ids offered in the
		 *                                              Add/Replace modal, in display order — built-in
		 *                                              'media_library'/'external_url', or a plugin-registered id
		 *                                              on window.edbsResourceSources (see src/js/metabox/resource-modal.js).
		 *                                              Defaults to both built-ins when omitted. A plugin mutates
		 *                                              an existing field's array (rather than only appending new
		 *                                              fields) to add a source to it — see DocumentPicker's
		 *                                              add_document_source() in the Pro repo for the pattern.
		 *     @type string|null   $title_template      Optional, 'resource' type only. The card's title — a
		 *                                              plain string, or one containing "{date}" (resolved
		 *                                              against the meeting's own edbs_meeting_date value
		 *                                              client-side; falls back to $label with no date set yet).
		 *     @type string|null   $item_noun           Optional, 'resource_list' type only. Singular noun for one
		 *                                              row, used in its "+ Add {noun}"/empty-state text (e.g.
		 *                                              "Document"). Defaults to "Document" for a top-level
		 *                                              'resource_list' field (ResourceListField); "Item" for one
		 *                                              rendered as an $attached field instead (AttachedRepeaterField
		 *                                              - see $attached_field_key above).
		 *     @type string|null   $default_item_label  Optional, 'resource_list' type only, and only meaningful
		 *                                              on an $attached field (AttachedRepeaterField) - a
		 *                                              top-level 'resource_list' field always starts a new row's
		 *                                              label blank instead. Seeds a newly added row's label
		 *                                              (e.g. "English" for a caption track) - still freely
		 *                                              editable after via the row's "Edit title". Defaults to ''
		 *                                              (shows as $empty_item_label until edited).
		 *     @type string|null   $empty_item_label    Optional, 'resource_list' type only. Shown in place of a
		 *                                              row with no label - defaults to "Untitled {$item_noun}".
		 *     @type string|null   $item_field_label    Optional, 'resource_list' type only, and only meaningful
		 *                                              on an $attached field - the top-level type's edit field
		 *                                              always uses a fixed "Document name" instead. The inline
		 *                                              edit field's own hidden (visually) accessible label - e.g.
		 *                                              "Language" for a caption track's row. Defaults to
		 *                                              $item_noun.
		 *     @type string|null   $insert_after        Optional. Another field's key — this field's row
		 *                                              renders immediately after that field's row instead
		 *                                              of at the end of the registry.
		 *     @type callable|null $sanitize_callback   Optional. Overrides the type's default save-time
		 *                                              sanitizer; receives the raw sanitize_text_field()'d
		 *                                              $_POST value. Not called when $saved_externally is set.
		 *     @type bool          $saved_externally    Optional, default false. MetaBox::save_meta() skips this
		 *                                               field entirely — use when its raw $_POST shape isn't a
		 *                                               plain scalar (e.g. a repeater's nested array, which
		 *                                               sanitize_text_field() would choke on) or when one field
		 *                                               writes to more than one meta key. The plugin owning the
		 *                                               field must save it itself, typically on
		 *                                               edbs_save_meeting_meta.
		 *     @type callable|null $render_callback     Optional, 'html' type only. `fn( \WP_Post $post ): string`
		 *                                              — computes this field's per-post markup (already escaped),
		 *                                              called by MetaBox::render_meta_box() in place of the
		 *                                              default get_post_meta( $post->ID, $key, true ) lookup, and
		 *                                              passed to the React app as this field's `value`.
		 *     @type string|null   $init_fn             Optional, 'html' type only. Name of a global JS function
		 *                                              (no arguments) that hydrates the rendered markup — e.g.
		 *                                              wires up an AJAX-backed widget already scanning the page
		 *                                              for its own root elements. Called once after every field's
		 *                                              markup exists in the DOM, deduped by name across fields
		 *                                              (see src/js/metabox/app.js) — safe for two fields sharing
		 *                                              one initializer that finds all of its own instances itself.
		 *     @type bool          $attached            Optional, default false. This field never gets its own
		 *                                              row - it's a `resource_list`-shaped store (same
		 *                                              `{ label, url }` shape and $saved_externally requirement)
		 *                                              that a 'resource' field surfaces inline via its own
		 *                                              $attached_field_key instead (see below - Pro's Recording
		 *                                              field/Caption Tracks is the only current example, in
		 *                                              ProMetaFields). Still included in the React app's values
		 *                                              state (so its data round-trips) and still needs its own
		 *                                              save handling like any other $saved_externally field -
		 *                                              it's only excluded from the rendered row list and from
		 *                                              $group heading placement.
		 *     @type string|null   $attached_field_key  Optional, 'resource' type only. Another field's key - one
		 *                                              with $attached set - whose repeater renders inside this
		 *                                              field's card (via AttachedRepeaterField, under the meta
		 *                                              line and above View/Replace/Remove - see ResourceCard's
		 *                                              $children slot). Entirely generic on the free plugin's
		 *                                              side - every piece of wording the attached field's own
		 *                                              rows show (its section heading, item noun, a new row's
		 *                                              default/empty label, the Add modal's title) comes from
		 *                                              that attached field's own descriptor (see
		 *                                              AttachedRepeaterField's docblock for the full list), not
		 *                                              from this one. Pro's Recording field is the only current
		 *                                              consumer (attaching VTT/SRT caption files), but nothing
		 *                                              about the mechanism itself is caption-specific.
		 *
		 * Pro plugin uses this to add its own fields (location, livestream/
		 * recording/cc-transcript URLs, documents, category) — either typed
		 * built-ins (url/textarea/text) or, for its existing AJAX-backed
		 * picker widgets, 'html' fields pairing $render_callback with $init_fn.
		 *
		 * @since 1.2.0
		 *
		 * @param array<int, array<string, mixed>> $fields Field descriptors.
		 */
		$fields = apply_filters( 'edbs_meeting_meta_fields', self::add_core_fields() );

		return self::order_fields( $fields );
	}

	/**
	 * Projects the field list into a JSON-safe shape for the React app —
	 * drops sanitize_callback/render_callback (closures/callables aren't
	 * serializable; both are only used server-side, by MetaBox::save_meta()
	 * and MetaBox::render_meta_box() respectively).
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function js_schema(): array {
		$schema = [];

		foreach ( self::all() as $field ) {
			$schema[] = [
				'key'              => $field['key'],
				'type'             => $field['type'] ?? 'text',
				'label'            => $field['label'] ?? '',
				'group'            => $field['group'] ?? null,
				'description'      => $field['description'] ?? null,
				'required'         => ! empty( $field['required'] ),
				'placeholder'      => $field['placeholder'] ?? null,
				'mediaPicker'      => ! empty( $field['media_picker'] ),
				'mediaTitle'       => $field['media_title'] ?? '',
				'initFn'           => $field['init_fn'] ?? null,
				'sources'          => $field['sources'] ?? null,
				'titleTemplate'    => $field['title_template'] ?? null,
				'itemNoun'         => $field['item_noun'] ?? null,
				'attached'         => ! empty( $field['attached'] ),
				'attachedFieldKey' => $field['attached_field_key'] ?? null,
				'defaultItemLabel' => $field['default_item_label'] ?? null,
				'emptyItemLabel'   => $field['empty_item_label'] ?? null,
				'itemFieldLabel'   => $field['item_field_label'] ?? null,
			];
		}

		return $schema;
	}

	/**
	 * The four fields the free plugin ships.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function add_core_fields(): array {
		return [
			[
				'key'         => 'edbs_meeting_date',
				'type'        => 'date',
				'label'       => __( 'Meeting Date', 'boardscribe' ),
				'description' => __( 'Required. Select the date the meeting was or will be held.', 'boardscribe' ),
				'required'    => true,
			],
			[
				'key'            => 'edbs_agenda_url',
				'type'           => 'resource',
				'label'          => __( 'Agenda', 'boardscribe' ),
				'media_title'    => __( 'Choose Agenda File', 'boardscribe' ),
				'title_template' => __( '{date} Board Meeting Agenda', 'boardscribe' ),
			],
			[
				'key'            => 'edbs_minutes_url',
				'type'           => 'resource',
				'label'          => __( 'Minutes', 'boardscribe' ),
				'media_title'    => __( 'Choose Minutes File', 'boardscribe' ),
				'title_template' => __( '{date} Board Meeting Minutes', 'boardscribe' ),
			],
			[
				'key'         => 'edbs_meeting_not_held',
				'type'        => 'checkbox',
				'label'       => __( 'Meeting Not Held', 'boardscribe' ),
				'description' => __( 'This meeting was not held.', 'boardscribe' ),
			],
		];
	}

	/**
	 * Resolves insert_after into final row order: fields without it keep
	 * their filtered array order, then each field with insert_after is
	 * spliced in directly after its target (appended at the end if the
	 * target key isn't found — a plugin ordering error shouldn't drop the
	 * field entirely).
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, array<string, mixed>> $fields Unordered field descriptors.
	 * @return array<int, array<string, mixed>>
	 */
	private static function order_fields( array $fields ): array {
		$ordered  = [];
		$deferred = [];

		foreach ( $fields as $field ) {
			if ( empty( $field['insert_after'] ) ) {
				$ordered[] = $field;
			} else {
				$deferred[] = $field;
			}
		}

		foreach ( $deferred as $field ) {
			$index = null;
			foreach ( $ordered as $i => $existing ) {
				if ( $existing['key'] === $field['insert_after'] ) {
					$index = $i;
					break;
				}
			}

			if ( null === $index ) {
				$ordered[] = $field;
				continue;
			}

			array_splice( $ordered, $index + 1, 0, [ $field ] );
		}

		return $ordered;
	}
}
