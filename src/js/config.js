// Global config injected by wp_localize_script() on the same script handle,
// so it is guaranteed to exist before this bundle executes.
const cfg = window.edmmConfig || {};

export const i18n = cfg.i18n || {};
export const apiBaseUrl = cfg.apiUrl || '';
