<?php
/**
 * Tests for SettingsPage::sanitize_settings().
 *
 * @package EqualizeDigital\MeetingMinutes
 */

use EqualizeDigital\MeetingMinutes\Admin\SettingsPage;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the Settings API sanitize_callback used when saving the
 * plugin's persistent settings form.
 */
class SettingsPageSanitizeSettingsTest extends TestCase {

	/**
	 * The SettingsPage instance under test.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Sets up a fresh instance for each test.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->settings_page = new SettingsPage();
	}

	/**
	 * When the checkbox key is present in the input, it's normalized to
	 * integer 1 regardless of the submitted value - the code only
	 * checks isset(), not the value itself.
	 *
	 * @dataProvider provide_present_checkbox_values
	 * @param mixed $value The submitted value for the present key.
	 */
	public function test_present_checkbox_normalizes_to_1( $value ): void {
		$result = $this->settings_page->sanitize_settings( [ 'delete_on_uninstall' => $value ] );

		$this->assertSame( 1, $result['delete_on_uninstall'] );
	}

	/**
	 * Data provider of "present" values isset() considers set.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_present_checkbox_values(): array {
		return [
			'checkbox on value' => [ 'on' ],
			'string zero'       => [ '0' ],
			'empty string'      => [ '' ],
			'boolean false'     => [ false ],
			'integer zero'      => [ 0 ],
		];
	}

	/**
	 * When the checkbox key is absent (unchecked checkboxes aren't
	 * submitted at all), it's normalized to integer 0.
	 */
	public function test_absent_checkbox_normalizes_to_0(): void {
		$result = $this->settings_page->sanitize_settings( [] );

		$this->assertSame( 0, $result['delete_on_uninstall'] );
	}

	/**
	 * Only the known delete_on_uninstall key is returned - any other
	 * submitted keys are dropped rather than passed through, since this
	 * acts as an allow-list for what can be stored in the option.
	 */
	public function test_unknown_input_keys_are_dropped(): void {
		$result = $this->settings_page->sanitize_settings(
			[
				'delete_on_uninstall' => '1',
				'unexpected_key'      => 'malicious_value',
			]
		);

		$this->assertSame( [ 'delete_on_uninstall' => 1 ], $result );
	}
}
