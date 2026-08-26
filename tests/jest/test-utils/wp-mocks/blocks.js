/**
 * Test-only stand-in for @wordpress/blocks, which isn't installed as a
 * real package (it's resolved to the wp.blocks global at build time - see
 * webpack.config.js's externals map). Jest has no such global, so this
 * module is wired in via jest.config.js's moduleNameMapper instead.
 *
 * Capturing the registered settings on the function itself lets a test
 * import the same mocked module and read back whatever the block under
 * test last registered, without any extra test-only exports the real
 * package doesn't have.
 *
 * @param {string} name     The block name.
 * @param {Object} settings The block settings, including edit()/save().
 * @return {Object} The settings, unchanged (matches the real API's return value).
 */
export function registerBlockType( name, settings ) {
	registerBlockType.mock = { name, settings };
	return settings;
}
