/* eslint-env node */
/**
 * Extends @wordpress/scripts' default webpack config with this plugin's
 * entry/output paths (the wp-scripts defaults of src/index.js → build/
 * clash with src/ holding the PHP classes).
 *
 * Three bundles are built:
 * - the frontend bundle (assets/build/boardscribe.js), enqueued by hand
 *   in BoardScribeShortcode.php with no *.asset.php — its externals
 *   are declared manually below;
 * - the block editor bundle (assets/build/block/index.js), consumed by
 *   block.json's editorScript. It keeps wp-scripts' default
 *   DependencyExtractionWebpackPlugin so register_block_type() can read
 *   the generated index.asset.php for dependencies/version;
 * - the shortcode builder bundle (assets/build/builder/index.js), the
 *   React app on the admin Shortcode Builder page, enqueued by
 *   SettingsPage.php. It also keeps DependencyExtractionWebpackPlugin
 *   and its generated index.asset.php.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const frontendConfig = {
	...defaultConfig,
	entry: {
		boardscribe: path.resolve( __dirname, 'src/js/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
		// The block/builder bundles below emit into subdirectories of this
		// output path; without the keep rule, this config's clean step
		// deletes them (the compilers run in parallel within one webpack run).
		clean: { keep: /^(block|builder)\// },
	},
	// Drop DependencyExtractionWebpackPlugin so no *.asset.php is emitted;
	// the externals it would have provided are declared by hand below.
	plugins: defaultConfig.plugins.filter(
		( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
	),
	// Map @wordpress/* imports to the wp.* globals WordPress ships instead
	// of bundling them. Every entry here needs its script handle (wp-*) in
	// the wp_enqueue_script() dependency list in BoardScribeShortcode.php.
	externals: {
		'@wordpress/escape-html': [ 'wp', 'escapeHtml' ],
		'@wordpress/i18n': [ 'wp', 'i18n' ],
	},
};

const blockConfig = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src/js/block/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build/block' ),
	},
};

const builderConfig = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src/js/builder/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build/builder' ),
	},
};

module.exports = [ frontendConfig, blockConfig, builderConfig ];
