<?php
/**
 * Plugin bootstrap smoke test.
 *
 * @package EqualizeDigital\MeetingMinutes
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
		$this->assertTrue( defined( 'EDMM_VERSION' ) );
		$this->assertTrue( defined( 'EDMM_FILE' ) );
		$this->assertTrue( defined( 'EDMM_DIR' ) );
		$this->assertTrue( defined( 'EDMM_URL' ) );
	}

	/**
	 * Plugin version constant has the expected format.
	 */
	public function test_plugin_version_format(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+/', EDMM_VERSION );
	}

	/**
	 * Plugin directory constant points to a real directory.
	 */
	public function test_plugin_dir_exists(): void {
		$this->assertDirectoryExists( EDMM_DIR );
	}
}
