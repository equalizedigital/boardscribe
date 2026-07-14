<?php
/**
 * Plugin bootstrap smoke test.
 *
 * @package EqualizeDigital\BoardScribe
 */

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Verifies the plugin loads and its constants are defined.
 */
class PluginTest extends TestCase {

	/**
	 * Plugin constants are defined after load.
	 */
	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'EDBS_VERSION' ) );
		$this->assertTrue( defined( 'EDBS_FILE' ) );
		$this->assertTrue( defined( 'EDBS_DIR' ) );
		$this->assertTrue( defined( 'EDBS_URL' ) );
	}

	/**
	 * Plugin version constant has the expected format.
	 */
	public function test_plugin_version_format(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+/', EDBS_VERSION );
	}

	/**
	 * Plugin directory constant points to a real directory.
	 */
	public function test_plugin_dir_exists(): void {
		$this->assertDirectoryExists( EDBS_DIR );
	}
}
