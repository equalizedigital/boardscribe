// Public extension registries. Both are plain window globals (not module
// exports) because add-ons (e.g. Pro) are separate scripts with no access to
// this bundle's module scope - they register by assigning to the globals
// before DOMContentLoaded. The `|| {}` / `|| []` initialisers make the
// registration order between this bundle and add-on scripts irrelevant.

// Registry for extra columns. Add-ons push column definitions here before
// DOMContentLoaded so they are included in the initial render.
//
// Each entry: { key: string, label: string, renderCell: function(meeting) → string }
// renderCell()'s return value, and label/getLabel()'s return value (used as
// raw column header HTML), are inserted directly as HTML - each must escape
// any untrusted data itself before returning.
window.edbsExtraColumns = window.edbsExtraColumns || [];

// Registry for display templates, keyed by template name. An instance selects
// its template via the shortcode's template="" attribute; unknown or missing
// names fall back to the built-in "table" template.
//
// Template contract:
//   render( data, instanceCfg, container )                      - required.
//       Render the meetings list into container (data is the REST response).
//   renderPagination( data, instanceCfg, container, goToPage )  - optional.
//       Replace the default pagination controls. Call goToPage( n ) to
//       navigate; core handles URL state and refetching.
//   renderInfo( data, instanceCfg, element )                    - optional.
//       Replace the default aria-live "Showing X to Y of Z" text.
//   focus( container, instanceCfg )                             - optional.
//       Move focus after a pagination-triggered re-render. The default
//       focuses the container itself, via a temporary tabindex="-1"
//       removed again right after - the table (or tables) a template
//       renders inside it are never made focusable themselves.
//   buildRequestUrl( instanceCfg, page )                        - optional.
//       Return the URL to fetch instead of the default query string -
//       core still performs the fetch, error handling, and JSON parsing.
//   request( instanceCfg, page )                                - optional.
//       Fully replace the request: return a Promise resolving to the data
//       passed to the renderers (different route, POST, multiple requests).
//       Takes precedence over buildRequestUrl. If the resolved data doesn't
//       keep the core shape, override the renderers that consume it; keep
//       numeric max_num_pages/current_page if relying on core pagination
//       or goToPage() navigation.
//
// instanceCfg.resolvedTemplate (set by core before render() is called) is
// the template name actually rendering, after the unknown/missing-name
// fallback above - it may differ from instanceCfg.template itself. Every
// template should add an "edbs-template-<resolvedTemplate>" class to its
// own root element(s) (e.g. each <table> it renders), so a site can target
// one template's output in CSS without needing the shortcode's class=""
// attribute. The built-in table uses "edbs-template-table".
//
// All rendered output is inserted as raw HTML - templates must escape any
// untrusted data themselves (window.edbsEscapeAttr is available for this).
//
// window.edbsBuildTable( meetings, instanceCfg ) returns a standard <table>
// (same columns, label resolution, hide*/tableClass handling, and
// window.edbsExtraColumns support as the built-in "table" template) as an
// HTML string. A template that renders multiple tables/sections (e.g. one
// table per year) can call this per section instead of re-implementing the
// same column-building logic - it already reads instanceCfg.resolvedTemplate
// itself, so the returned table carries the calling template's own
// edbs-template-<name> class.
//
// window.edbsResolveColumns( instanceCfg ) is the layer beneath that, for a
// template whose output isn't a table at all (a stacked list, cards, a
// calendar). It returns the visible columns in render order - the same
// order, label resolution, hide* handling and window.edbsExtraColumns
// support edbsBuildTable() uses, since edbsBuildTable() is built on it -
// leaving the markup entirely to the caller. Each entry:
//   key         - 'title' | 'date' | 'agenda' | 'minutes', or the extra
//                 column's own key.
//   label       - The label as plain text, for attribute and text contexts.
//                 Escape it at the insertion point. An extra column whose
//                 header is markup is rendered down to its text here, so
//                 a text context shows "Where", not "<em>Where</em>".
//   labelHtml   - The label as header HTML, ready to insert unescaped -
//                 already escaped for core columns, and the registrant's
//                 own raw header HTML for extra columns (see the
//                 edbsExtraColumns contract above).
//   isRowHeader - True for the one column that identifies the row (the
//                 title, or the date when the title is hidden; no entry
//                 carries it when both are hidden). A non-table template
//                 should give this column whatever prominence its own
//                 semantics call for - a heading, say - rather than
//                 reproducing role="rowheader".
//   render      - function( meeting ) → cell HTML, inserted as raw
//                 pre-escaped HTML on the same contract as everything else
//                 here.
window.edbsTemplates = window.edbsTemplates || {};

// Lifecycle events. Each instance (instance.js) dispatches namespaced,
// bubbling CustomEvents on its own .edbs-boardscribe-wrap container - bind
// with container.addEventListener( name, handler ) for one instance, or
// document.addEventListener( name, handler ) to catch every instance on the
// page (event.target is the container that fired it). No import/build step
// needed, matching the plain-global registries above. event.detail always
// includes instanceCfg; see each event for the rest of its shape.
//
//   edbs:table-rendered    { data, instanceCfg }         - after the
//       template's render() call finishes updating the table/list markup.
//   edbs:info-rendered     { data, instanceCfg }         - after the
//       aria-live "Showing X to Y of Z" text is updated. Only fires when
//       the instance has an info element.
//   edbs:pagination-rendered { data, instanceCfg }       - after the
//       pagination controls are (re)rendered.
//   edbs:page-changed      { page, instanceCfg }         - when the user
//       triggers navigation to a new page (goToPage()), before the
//       refetch/re-render it kicks off - `page` is the 1-based target page.
//   edbs:fetch-error       { error, instanceCfg }        - when the
//       request for a page's data fails (network error or non-OK response).
//
// `data` is the parsed JSON response. buildRequestUrl() only changes the
// fetch URL - core does not validate or transform what comes back, so the
// endpoint it points to must return the shape the active renderers expect.
// A template's own request() override can resolve to a different shape
// entirely, in which case any renderers it doesn't also override must be
// replaced too.
