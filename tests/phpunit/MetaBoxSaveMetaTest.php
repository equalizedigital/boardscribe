<?php
/**
 * Tests for MetaBox::save_meta().
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Covers the meta box save handler's nonce/capability gating and
 * per-field sanitization - this is the plugin's only direct-$_POST
 * write path, so it's the highest-value target for authorization and
 * sanitization regression coverage.
 */
class MetaBoxSaveMetaTest extends TestCase {

	/**
	 * The MetaBox instance under test.
	 *
	 * @var MetaBox
	 */
	private MetaBox $meta_box;

	/**
	 * The post ID used across tests.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Sets up a fresh MetaBox instance, test post, and valid POST/nonce
	 * baseline for each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->meta_box = new MetaBox();
		$this->post_id  = self::factory()->post->create( [ 'post_type' => 'edbs_meeting' ] );

		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor_id );

		$_POST = [
			'edbs_meeting_meta_nonce' => wp_create_nonce( 'edbs_save_meeting_meta' ),
			// MetaBoxApp renders this itself alongside every field's own
			// input - present here so every other test in this file
			// exercises the same baseline a real, successfully-rendered
			// save would have. See test_meta_box_not_rendered_saves_nothing()
			// for the guard this covers.
			'edbs_meta_box_rendered'  => '1',
		];
	}

	/**
	 * Resets $_POST and the current user after each test.
	 */
	public function tear_down(): void {
		$_POST = [];
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Without the nonce field present at all, nothing is saved.
	 */
	public function test_missing_nonce_saves_nothing(): void {
		$_POST = [ 'edbs_meeting_date' => '2024-03-15' ];

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * An invalid/forged nonce is rejected.
	 */
	public function test_invalid_nonce_saves_nothing(): void {
		$_POST['edbs_meeting_meta_nonce'] = 'not-a-real-nonce';
		$_POST['edbs_meeting_date']       = '2024-03-15';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A valid nonce but a user without edit_post capability for this
	 * post is rejected.
	 *
	 * The nonce must be generated for the same user whose capability is
	 * under test (a subscriber, who can't edit this post) - reusing the
	 * editor's nonce from set_up() while switching to a different user
	 * would fail wp_verify_nonce() first, meaning the capability check
	 * this test claims to cover would never actually run.
	 */
	public function test_user_without_capability_saves_nothing(): void {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$_POST['edbs_meeting_meta_nonce'] = wp_create_nonce( 'edbs_save_meeting_meta' );
		$_POST['edbs_meeting_date']       = '2024-03-15';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A well-formed Y-m-d date is saved as-is.
	 */
	public function test_valid_date_is_saved(): void {
		$_POST['edbs_meeting_date'] = '2024-03-15';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '2024-03-15', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A date that doesn't parse as real Y-m-d (e.g. month 13) is
	 * rejected rather than saved.
	 */
	public function test_invalid_date_is_not_saved(): void {
		$_POST['edbs_meeting_date'] = '2024-13-45';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * A date string that isn't in Y-m-d format at all is rejected, not
	 * reformatted or partially accepted.
	 */
	public function test_wrong_format_date_is_not_saved(): void {
		$_POST['edbs_meeting_date'] = '03/15/2024';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_date', true ) );
	}

	/**
	 * The agenda URL is run through esc_url_raw() before saving.
	 */
	public function test_agenda_url_is_sanitized(): void {
		$input                            = 'https://example.com/agenda.pdf"><script>alert(1)</script>';
		$_POST['edbs_agenda_url'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		$saved = get_post_meta( $this->post_id, 'edbs_agenda_url', true );
		$this->assertSame( esc_url_raw( $input ), $saved );
	}

	/**
	 * The minutes URL is run through esc_url_raw() before saving.
	 */
	public function test_minutes_url_is_sanitized(): void {
		$input                             = 'javascript:alert(1)';
		$_POST['edbs_minutes_url'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		$saved = get_post_meta( $this->post_id, 'edbs_minutes_url', true );
		$this->assertSame( esc_url_raw( $input ), $saved );
	}

	/**
	 * The "not held" checkbox saves '1' when present in $_POST.
	 */
	public function test_not_held_checkbox_checked_saves_1(): void {
		$_POST['edbs_meeting_not_held'] = '1';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '1', get_post_meta( $this->post_id, 'edbs_meeting_not_held', true ) );
	}

	/**
	 * The "not held" checkbox saves an empty string when absent from
	 * $_POST, since unchecked checkboxes aren't submitted at all.
	 */
	public function test_not_held_checkbox_unchecked_saves_empty_string(): void {
		// Deliberately not setting edbs_meeting_not_held in $_POST.
		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_meeting_not_held', true ) );
	}

	/**
	 * A save whose $_POST is missing edbs_meta_box_rendered (the React
	 * app failed to commit - a throwing custom control, a missing/stale
	 * build, or a WP version lacking a component the bundle needs) saves
	 * nothing at all, rather than treating every field as blank. Verified
	 * against the exact real-world symptom (PRO-1394): a meeting already
	 * marked "not held" must NOT have that checkbox silently cleared just
	 * because the box that would have re-submitted it never rendered.
	 */
	public function test_meta_box_not_rendered_saves_nothing(): void {
		update_post_meta( $this->post_id, 'edbs_meeting_not_held', '1' );

		unset( $_POST['edbs_meta_box_rendered'] );
		// No edbs_meeting_not_held in $_POST either - exactly what an
		// empty-rendered box would submit.

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '1', get_post_meta( $this->post_id, 'edbs_meeting_not_held', true ) );
	}

	/**
	 * The action does not fire when edbs_meta_box_rendered is missing,
	 * since the method returns early before reaching it - protects a
	 * plugin's own save handler for its own React-rendered fields (e.g.
	 * Pro's Supporting Documents repeater) the same way.
	 */
	public function test_save_meeting_meta_action_does_not_fire_when_meta_box_not_rendered(): void {
		unset( $_POST['edbs_meta_box_rendered'] );

		$fired    = false;
		$callback = static function () use ( &$fired ): void {
			$fired = true;
		};
		add_action( 'edbs_save_meeting_meta', $callback );

		$this->meta_box->save_meta( $this->post_id );

		remove_action( 'edbs_save_meeting_meta', $callback );

		$this->assertFalse( $fired );
	}

	/**
	 * A 'textarea' field preserves line breaks - sanitize_textarea_field()
	 * rather than sanitize_text_field(), which would flatten a multi-line
	 * value to one line (PRO-1394).
	 */
	public function test_textarea_field_preserves_line_breaks(): void {
		$callback = static function ( array $fields ): array {
			$fields[] = [
				'key'   => 'test_textarea',
				'type'  => 'textarea',
				'label' => 'Test Textarea',
			];
			return $fields;
		};
		add_filter( 'edbs_meeting_meta_fields', $callback );

		$input                        = "Line one\nLine two\nLine three";
		$_POST['test_textarea'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		remove_filter( 'edbs_meeting_meta_fields', $callback );

		$this->assertSame( $input, get_post_meta( $this->post_id, 'test_textarea', true ) );
	}

	/**
	 * A 'textarea' field's own sanitize_callback receives the
	 * textarea-sanitized value (line breaks intact), not the already
	 * text-flattened one (PRO-1394).
	 */
	public function test_textarea_field_sanitize_callback_receives_unflattened_value(): void {
		$received = null;
		$callback = static function ( array $fields ) use ( &$received ): array {
			$fields[] = [
				'key'               => 'test_textarea_callback',
				'type'              => 'textarea',
				'label'             => 'Test Textarea Callback',
				'sanitize_callback' => static function ( $value ) use ( &$received ) {
					$received = $value;
					return $value;
				},
			];
			return $fields;
		};
		add_filter( 'edbs_meeting_meta_fields', $callback );

		$input                                   = "Line one\nLine two";
		$_POST['test_textarea_callback'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		remove_filter( 'edbs_meeting_meta_fields', $callback );

		$this->assertSame( $input, $received );
	}

	/**
	 * A field marked saved_externally is skipped by save_field() entirely —
	 * the plugin owning it is expected to save it itself, typically on the
	 * edbs_save_meeting_meta action that fires right after.
	 */
	public function test_saved_externally_field_is_not_auto_saved(): void {
		$callback = static function ( array $fields ): array {
			$fields[] = [
				'key'              => 'pro_repeater',
				'type'             => 'html',
				'label'            => 'Pro Repeater',
				'saved_externally' => true,
			];
			return $fields;
		};
		add_filter( 'edbs_meeting_meta_fields', $callback );

		$_POST['pro_repeater'] = 'should-not-be-saved-by-the-generic-loop';

		$this->meta_box->save_meta( $this->post_id );

		remove_filter( 'edbs_meeting_meta_fields', $callback );

		$this->assertSame( '', get_post_meta( $this->post_id, 'pro_repeater', true ) );
	}

	/**
	 * The edbs_save_meeting_meta action fires after a successful save,
	 * so Pro plugin can save its own additional meta in the same request.
	 */
	public function test_save_meeting_meta_action_fires_on_success(): void {
		$fired_with_post_id = null;
		$callback            = static function ( int $post_id ) use ( &$fired_with_post_id ): void {
			$fired_with_post_id = $post_id;
		};
		add_action( 'edbs_save_meeting_meta', $callback );

		$this->meta_box->save_meta( $this->post_id );

		remove_action( 'edbs_save_meeting_meta', $callback );

		$this->assertSame( $this->post_id, $fired_with_post_id );
	}

	/**
	 * The action does not fire when the nonce is missing/invalid, since
	 * the method returns early before reaching it.
	 */
	public function test_save_meeting_meta_action_does_not_fire_without_valid_nonce(): void {
		$_POST = []; // No nonce at all.

		$fired    = false;
		$callback = static function () use ( &$fired ): void {
			$fired = true;
		};
		add_action( 'edbs_save_meeting_meta', $callback );

		$this->meta_box->save_meta( $this->post_id );

		remove_action( 'edbs_save_meeting_meta', $callback );

		$this->assertFalse( $fired );
	}

	/**
	 * A 'resource' field's `{key}_source` sibling meta is saved verbatim
	 * (through sanitize_key(), not the field's own esc_url_raw() path) so
	 * a plugin-registered source id round-trips - see
	 * MetaBox::save_resource_source()'s docblock.
	 */
	public function test_resource_field_source_is_saved(): void {
		$_POST['edbs_agenda_url']        = 'https://example.com/agenda.pdf';
		$_POST['edbs_agenda_url_source'] = 'media_library';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( 'media_library', get_post_meta( $this->post_id, 'edbs_agenda_url_source', true ) );
	}

	/**
	 * The `{key}_source` sibling meta is sanitized with sanitize_key()
	 * rather than left as raw input.
	 */
	public function test_resource_field_source_is_sanitized(): void {
		$_POST['edbs_agenda_url']        = 'https://example.com/agenda.pdf';
		$_POST['edbs_agenda_url_source'] = 'Media Library<script>!';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame(
			sanitize_key( 'Media Library<script>!' ),
			get_post_meta( $this->post_id, 'edbs_agenda_url_source', true )
		);
	}

	/**
	 * When `{key}_source` isn't present in $_POST at all (e.g. a legacy
	 * client that doesn't send it), no sibling meta is written.
	 */
	public function test_resource_field_source_not_saved_when_absent(): void {
		$_POST['edbs_agenda_url'] = 'https://example.com/agenda.pdf';
		// Deliberately not setting edbs_agenda_url_source.

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_agenda_url_source', true ) );
	}

	/**
	 * A 'resource' field's `{key}_edit_url` sibling meta is run through
	 * esc_url_raw() before saving, same as the field's own url value -
	 * see MetaBox::save_resource_edit_url()'s docblock.
	 */
	public function test_resource_field_edit_url_is_sanitized(): void {
		$input                             = 'https://example.com/wp-admin/post.php?post=42&action=edit"><script>alert(1)</script>';
		$_POST['edbs_agenda_url']          = 'https://example.com/agenda.pdf';
		$_POST['edbs_agenda_url_edit_url'] = $input;

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame(
			esc_url_raw( $input ),
			get_post_meta( $this->post_id, 'edbs_agenda_url_edit_url', true )
		);
	}

	/**
	 * When `{key}_edit_url` isn't present in $_POST (the field's source
	 * has no underlying editable post, e.g. Media Library/External URL),
	 * no sibling meta is written.
	 */
	public function test_resource_field_edit_url_not_saved_when_absent(): void {
		$_POST['edbs_agenda_url'] = 'https://example.com/agenda.pdf';
		// Deliberately not setting edbs_agenda_url_edit_url.

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_agenda_url_edit_url', true ) );
	}

	/**
	 * A 'url'/'resource' field submitted as an array (a URL field name
	 * edited to "{$key}[]") is silently skipped rather than fataling -
	 * esc_url_raw() calls ltrim() internally, which is a TypeError in
	 * PHP 8+ given an array.
	 */
	public function test_url_field_submitted_as_array_does_not_fatal(): void {
		$_POST['edbs_agenda_url'] = [ 'https://example.com/agenda.pdf' ];

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_agenda_url', true ) );
	}

	/**
	 * The `{key}_edit_url` sibling meta gets the same array-input guard as
	 * the field's own url value.
	 */
	public function test_resource_field_edit_url_submitted_as_array_does_not_fatal(): void {
		$_POST['edbs_agenda_url']          = 'https://example.com/agenda.pdf';
		$_POST['edbs_agenda_url_edit_url'] = [ 'https://example.com/wp-admin/post.php?post=42&action=edit' ];

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_agenda_url_edit_url', true ) );
	}

	/**
	 * A 'resource' field's `{key}_document_id` sibling meta is saved
	 * verbatim (through absint()) - see
	 * MetaBox::save_resource_document_id()'s docblock.
	 */
	public function test_resource_field_document_id_is_saved(): void {
		$_POST['edbs_agenda_url']             = 'https://example.com/board-documents/agenda-2026-03/';
		$_POST['edbs_agenda_url_document_id'] = '42';

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '42', get_post_meta( $this->post_id, 'edbs_agenda_url_document_id', true ) );
	}

	/**
	 * When `{key}_document_id` isn't present in $_POST (the field's
	 * source has no underlying linked post, e.g. Media Library/External
	 * URL), no sibling meta is written.
	 */
	public function test_resource_field_document_id_not_saved_when_absent(): void {
		$_POST['edbs_agenda_url'] = 'https://example.com/agenda.pdf';
		// Deliberately not setting edbs_agenda_url_document_id.

		$this->meta_box->save_meta( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, 'edbs_agenda_url_document_id', true ) );
	}

	/**
	 * A 'resource_list' field that a plugin forgot to mark
	 * saved_externally is not saved by the generic loop (its $_POST value
	 * is a repeater array, not a plain scalar) - it triggers a
	 * _doing_it_wrong() notice rather than falling through to
	 * sanitize_text_field() on an array.
	 */
	public function test_misconfigured_resource_list_field_is_not_auto_saved(): void {
		$this->setExpectedIncorrectUsage( 'EqualizeDigital\BoardScribe\Admin\MetaBox::save_field' );

		$callback = static function ( array $fields ): array {
			$fields[] = [
				'key'   => 'pro_documents',
				'type'  => 'resource_list',
				'label' => 'Pro Documents',
				// Deliberately missing saved_externally => true.
			];
			return $fields;
		};
		add_filter( 'edbs_meeting_meta_fields', $callback );

		$_POST['pro_documents'] = [
			[
				'label' => 'Budget',
				'url'   => 'https://example.com/budget.pdf',
			],
		];

		$this->meta_box->save_meta( $this->post_id );

		remove_filter( 'edbs_meeting_meta_fields', $callback );

		$this->assertSame( '', get_post_meta( $this->post_id, 'pro_documents', true ) );
	}

	/**
	 * An 'attached' field that a plugin forgot to mark saved_externally
	 * is not auto-saved either, regardless of its own $type - the
	 * attached mechanism itself is always externally saved.
	 */
	public function test_misconfigured_attached_field_is_not_auto_saved(): void {
		$this->setExpectedIncorrectUsage( 'EqualizeDigital\BoardScribe\Admin\MetaBox::save_field' );

		$callback = static function ( array $fields ): array {
			$fields[] = [
				'key'      => 'pro_attached_list',
				'type'     => 'text',
				'label'    => 'Pro Attached List',
				'attached' => true,
				// Deliberately missing saved_externally => true.
			];
			return $fields;
		};
		add_filter( 'edbs_meeting_meta_fields', $callback );

		$_POST['pro_attached_list'] = [ [ 'label' => 'English', 'url' => 'https://example.com/en.vtt' ] ];

		$this->meta_box->save_meta( $this->post_id );

		remove_filter( 'edbs_meeting_meta_fields', $callback );

		$this->assertSame( '', get_post_meta( $this->post_id, 'pro_attached_list', true ) );
	}
}
