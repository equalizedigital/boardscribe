/* eslint-env node */
/**
 * Extends @wordpress/scripts' default webpack config with this plugin's
 * entry/output paths (the wp-scripts defaults of src/index.js → build/
 * clash with src/ holding the PHP classes).
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'meeting-minutes': path.resolve( __dirname, 'assets/src/js/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
	// Drop DependencyExtractionWebpackPlugin so no *.asset.php is emitted;
	// the externals it would have provided are declared by hand below.
	plugins: defaultConfig.plugins.filter(
		( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
	),
	// Map @wordpress/* imports to the wp.* globals WordPress ships instead
	// of bundling them. Every entry here needs its script handle (wp-*) in
	// the wp_enqueue_script() dependency list in MeetingMinutesShortcode.php.
	externals: {
		'@wordpress/escape-html': [ 'wp', 'escapeHtml' ],
		'@wordpress/i18n': [ 'wp', 'i18n' ],
	},
};
