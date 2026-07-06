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
window.edmmExtraColumns = window.edmmExtraColumns || [];

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
//       focuses the first [tabindex="0"] element in the container.
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
// template should add an "edmm-template-<resolvedTemplate>" class to its
// own root element(s) (e.g. each <table> it renders), so a site can target
// one template's output in CSS without needing the shortcode's class=""
// attribute. The built-in table uses "edmm-template-table".
//
// All rendered output is inserted as raw HTML - templates must escape any
// untrusted data themselves (window.edmmEscapeAttr is available for this).
window.edmmTemplates = window.edmmTemplates || {};
