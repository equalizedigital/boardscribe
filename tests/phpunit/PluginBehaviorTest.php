<?php
/**
 * Tests for Plugin's behavior beyond the bootstrap smoke test.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

use EqualizeDigital\MeetingMinutes\Plugin;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers Plugin::get_instance() singleton behavior, get_setting()'s
 * fallback chain (stored option -> caller-supplied default ->
 * DEFAULTS constant), and register_edacp_filters() (the Accessibility
 * Checker Pro integration point).
 */
class PluginBehaviorTest extends TestCase {

	/**
	 * Deletes the edmm_settings option before/after each test so
	 * get_setting() tests start from a known, empty state.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( 'edmm_settings' );
	}

	/**
	 * Tears down by deleting the option again.
	 */
	public function tear_down(): void {
		delete_option( 'edmm_settings' );
		parent::tear_down();
	}

	/**
	 * get_instance() always returns the same instance.
	 */
	public function test_get_instance_returns_singleton(): void {
		$this->assertSame( Plugin::get_instance(), Plugin::get_instance() );
	}

	/**
	 * A value present in the stored edmm_settings option is returned.
	 */
	public function test_get_setting_returns_stored_value(): void {
		update_option( 'edmm_settings', [ 'delete_on_uninstall' => 1 ] );

		$this->assertSame( 1, Plugin::get_setting( 'delete_on_uninstall' ) );
	}

	/**
	 * With no stored option and no key in DEFAULTS, an explicit
	 * caller-supplied default is returned.
	 */
	public function test_get_setting_returns_caller_default_for_unknown_key(): void {
		$this->assertSame( 'fallback', Plugin::get_setting( 'not_a_real_setting', 'fallback' ) );
	}

	/**
	 * With no stored option and no caller-supplied default, a key that
	 * exists in the DEFAULTS constant falls back to it.
	 */
	public function test_get_setting_falls_back_to_defaults_constant(): void {
		$this->assertSame( 0, Plugin::get_setting( 'delete_on_uninstall' ) );
	}

	/**
	 * With no stored option, no caller default, and no matching
	 * DEFAULTS key, null is returned rather than throwing.
	 */
	public function test_get_setting_returns_null_for_completely_unknown_key(): void {
		$this->assertNull( Plugin::get_setting( 'not_a_real_setting' ) );
	}

	/**
	 * A stored value takes priority over both the caller-supplied
	 * default and the DEFAULTS constant.
	 */
	public function test_get_setting_stored_value_takes_priority_over_default(): void {
		update_option( 'edmm_settings', [ 'delete_on_uninstall' => 1 ] );

		$this->assertSame( 1, Plugin::get_setting( 'delete_on_uninstall', 0 ) );
	}

	/**
	 * register_edacp_filters() adds both meeting-minutes link filters to
	 * the list Accessibility Checker Pro consumes, without disturbing
	 * any filters already present.
	 */
	public function test_register_edacp_filters_adds_expected_filter_names(): void {
		$plugin  = Plugin::get_instance();
		$result  = $plugin->register_edacp_filters( [ 'existing_filter' ] );

		$this->assertContains( 'existing_filter', $result );
		$this->assertContains( 'edmm_meeting_minutes_link', $result );
		$this->assertContains( 'edmm_meeting_agenda_link', $result );
	}

	/**
	 * edmm_loaded already fired during the normal plugins_loaded
	 * bootstrap sequence, since Plugin::boot() runs there - this is
	 * documented as the Pro plugin's sole entry point, so it must have
	 * actually fired by the time any test runs.
	 */
	public function test_edmm_loaded_action_fired_during_bootstrap(): void {
		$this->assertGreaterThanOrEqual( 1, did_action( 'edmm_loaded' ) );
	}
}
