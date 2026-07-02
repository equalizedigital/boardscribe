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
};
