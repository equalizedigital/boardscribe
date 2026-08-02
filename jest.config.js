/* eslint-env node */
/**
 * Extends the default wp-scripts unit-test config (jsdom, babel-jest, no
 * config needed for plain-JS ESM) with module mappings for the handful
 * of WordPress packages this codebase only ever consumes as build-time
 * externals (see webpack.config.js's externals map) - they resolve to
 * wp.* globals in the browser, which don't exist under Jest, so tests
 * that touch block/builder code need a stand-in. The element, hooks,
 * i18n, and escape-html packages are real installed packages and need
 * no mapping.
 */
const baseConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...baseConfig,
	moduleNameMapper: {
		...baseConfig.moduleNameMapper,
		'^@wordpress/blocks$': '<rootDir>/tests/jest/test-utils/wp-mocks/blocks.js',
		'^@wordpress/block-editor$': '<rootDir>/tests/jest/test-utils/wp-mocks/block-editor.js',
		'^@wordpress/components$': '<rootDir>/tests/jest/test-utils/wp-mocks/components.js',
		'^@wordpress/server-side-render$': '<rootDir>/tests/jest/test-utils/wp-mocks/server-side-render.js',
	},
};
