<?php
/**
 * CSV importer for BoardScribe.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Import;

use EqualizeDigital\BoardScribe\Admin\MetaBox;

/**
 * Provides a bulk CSV import admin page under the BoardScribe menu.
 *
 * Core recognised columns (see columns()): title, date, agenda_url,
 * minutes_url, not_held, publish_date.
 *
 * - title        : Optional. A blank title falls back to the same
 *                  auto-generated "Board Meeting - {date}" title
 *                  (MetaBox::generate_default_title(), filterable via
 *                  edbs_default_meeting_title) used when a meeting is
 *                  saved without one in the admin meta box.
 * - date         : Y-m-d format (e.g. 2024-03-15)
 * - not_held     : "yes" or "1" to mark the meeting as not held
 * - publish_date : Optional. Any date/time PHP's native date parser
 *                  understands — e.g. 2024-03-15, 2024-03-15 14:30:00,
 *                  2024-03-15T14:30:00-05:00, or "March 15, 2024
 *                  2:30pm" — not one fixed format; see
 *                  parse_publish_date(). Sets the WordPress post's
 *                  actual publish date/time (post_date), which
 *                  otherwise defaults to the moment of import — use
 *                  this to backdate historical meetings so they sort
 *                  correctly by publish date elsewhere in WordPress
 *                  (e.g. the post list, RSS feeds). Does not affect the
 *                  meeting date/sort order used by the plugin itself,
 *                  which is always driven by the date column above.
 *
 * A plugin extending the importer (e.g. Pro, for its location/livestream/
 * recording/transcript/documents/category fields) appends its own columns
 * via the edbs_csv_import_columns filter and reads their raw per-row values
 * off the edbs_csv_import_row_meta action.
 */
class CsvImporter {

	/**
	 * Hooks the importer into WordPress.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'edbs_settings_tabs', [ $this, 'add_settings_tab' ] );
		add_action( 'edbs_settings_tab_content_import', [ $this, 'render_page' ] );
		add_action( 'admin_post_edbs_csv_import', [ $this, 'handle_upload' ] );
	}

	/**
	 * Returns the recognised CSV columns, keyed by column key.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array{required: bool, notes: string}>
	 */
	public function columns(): array {
		$columns = [
			'title'        => [
				'required' => false,
				'notes'    => __( 'Meeting title. If blank, auto-generated from the date (e.g. "Board Meeting - March 15, 2025").', 'boardscribe' ),
			],
			'date'         => [
				'required' => true,
				'notes'    => __( 'Format: YYYY-MM-DD', 'boardscribe' ),
			],
			'agenda_url'   => [
				'required' => false,
				'notes'    => __( 'Full URL to agenda file', 'boardscribe' ),
			],
			'minutes_url'  => [
				'required' => false,
				'notes'    => __( 'Full URL to minutes file', 'boardscribe' ),
			],
			'not_held'     => [
				'required' => false,
				'notes'    => sprintf(
					/* translators: %s: the literal CSV values accepted ("yes / 1 / true") — these are fixed tokens the importer checks for, not to be translated. */
					__( 'Accepted values: %s', 'boardscribe' ),
					'yes / 1 / true'
				),
			],
			'publish_date' => [
				'required' => false,
				'notes'    => sprintf(
					/* translators: %s: example date/time formats — these are literal examples, not to be translated. */
					__( 'Any recognizable date or date/time (e.g. %s). Sets the actual WordPress publish date instead of the moment of import — use this to backdate historical meetings. Does not affect the meeting date/sort order above.', 'boardscribe' ),
					'2024-03-15, 2024-03-15 14:30:00, or 2024-03-15T14:30:00-05:00'
				),
			],
		];

		/**
		 * Filters the recognised CSV import columns, keyed by column key.
		 *
		 * Each entry is `[ 'required' => bool, 'notes' => string ]`.
		 * Appending a column here makes it appear in the column-reference
		 * table shown on the Import tab and (when required) enforced during
		 * import. Read a column's raw per-row value off the
		 * edbs_csv_import_row_meta action rather than here — this filter
		 * only controls the recognised column list and its documentation.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, array{required: bool, notes: string}> $columns Column descriptors, keyed by column key.
		 */
		return apply_filters( 'edbs_csv_import_columns', $columns );
	}

	/**
	 * Registers the Import tab on the settings page.
	 *
	 * Inserts the tab just before the "Support" tab when present, otherwise
	 * appends it. Its content renders on the matching
	 * edbs_settings_tab_content_import action (see render_page()).
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, array{icon:string,label:string}> $tabs Existing tab map.
	 * @return array<string, array{icon:string,label:string}>
	 */
	public function add_settings_tab( array $tabs ): array {
		$import = [
			'import' => [
				'icon'  => 'dashicons-upload',
				'label' => __( 'Import', 'boardscribe' ),
			],
		];

		$position = array_search( 'support', array_keys( $tabs ), true );
		if ( false === $position ) {
			return array_merge( $tabs, $import );
		}

		return array_merge(
			array_slice( $tabs, 0, $position, true ),
			$import,
			array_slice( $tabs, $position, null, true )
		);
	}

	/**
	 * Handles the CSV file upload form submission.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_upload(): void {
		if ( ! check_admin_referer( 'edbs_csv_import', 'edbs_csv_import_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'boardscribe' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'boardscribe' ) );
		}

		if ( empty( $_FILES['edbs_csv']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'edbs_import_error', 'no_file', $this->get_page_url() ) );
			exit;
		}

		$file = $_FILES['edbs_csv']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value is a server-generated tmp path, validated by mime_content_type() below.

		// Validate MIME type.
		$mime = mime_content_type( $file );
		if ( ! in_array( $mime, [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ], true ) ) {
			wp_safe_redirect( add_query_arg( 'edbs_import_error', 'invalid_type', $this->get_page_url() ) );
			exit;
		}

		$result = $this->process_csv( $file );

		wp_safe_redirect(
			add_query_arg(
				[
					'edbs_import_success' => $result['imported'],
					'edbs_import_skipped' => $result['skipped'],
				],
				$this->get_page_url()
			)
		);
		exit;
	}

	/**
	 * Processes the uploaded CSV file and creates posts.
	 *
	 * @since 1.1.0
	 *
	 * @param string $file Path to the temporary uploaded file.
	 * @return array{ imported: int, skipped: int }
	 */
	private function process_csv( string $file ): array {
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			return [
				'imported' => 0,
				'skipped'  => 0,
			];
		}

		$required_columns = array_keys( array_filter( $this->columns(), fn( array $column ): bool => ! empty( $column['required'] ) ) );
		$meta_box         = new MetaBox();

		$imported = 0;
		$skipped  = 0;
		$headers  = null;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- idiomatic fgetcsv loop pattern.
			// First row: extract and normalise headers. Strips a leading
			// UTF-8 BOM from the first header - common in CSVs exported from
			// Excel - which would otherwise make the "title" column
			// unrecognisable and skip every row.
			if ( null === $headers ) {
				$headers    = array_map( 'strtolower', array_map( 'trim', $row ) );
				$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
				continue;
			}

			if ( count( $row ) !== count( $headers ) ) {
				++$skipped;
				continue;
			}

			$data = array_combine( $headers, array_map( 'trim', $row ) );

			// Validate required columns.
			foreach ( $required_columns as $col ) {
				if ( ! isset( $data[ $col ] ) || '' === trim( $data[ $col ] ) ) {
					++$skipped;
					continue 2;
				}
			}

			// Validate date.
			$date     = sanitize_text_field( trim( $data['date'] ) );
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $date );
			if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date ) {
				++$skipped;
				continue;
			}

			// Validate the optional publish_date column, if present. Accepts
			// any date/time format PHP's date parser understands (bare dates,
			// date+time, ISO 8601 with or without a UTC offset, "March 15,
			// 2024 2:30pm", etc.) rather than a single fixed format.
			$publish_date = null;
			if ( isset( $data['publish_date'] ) && '' !== trim( $data['publish_date'] ) ) {
				$publish_date = $this->parse_publish_date( sanitize_text_field( trim( $data['publish_date'] ) ) );
				if ( null === $publish_date ) {
					++$skipped;
					continue;
				}
			}

			$title = sanitize_text_field( trim( $data['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = $meta_box->generate_default_title( $date );
			}

			$post_args = [
				'post_title'  => $title,
				'post_type'   => 'edbs_meeting',
				'post_status' => 'publish',
			];

			// Backdates the post's actual WordPress publish date/time (post_date)
			// to the given value instead of the moment of import, so historical
			// imports sort correctly by publish date elsewhere in WordPress (the
			// post list, RSS feeds). Does not affect the plugin's own meeting
			// date/sort order, which is always driven by edbs_meeting_date above.
			if ( null !== $publish_date ) {
				$post_args['post_date']     = $publish_date['local'];
				$post_args['post_date_gmt'] = $publish_date['gmt'];
			}

			$post_id = wp_insert_post( $post_args, true );

			if ( is_wp_error( $post_id ) ) {
				++$skipped;
				continue;
			}

			update_post_meta( $post_id, 'edbs_meeting_date', $date );

			if ( ! empty( $data['agenda_url'] ) ) {
				update_post_meta( $post_id, 'edbs_agenda_url', esc_url_raw( trim( $data['agenda_url'] ) ) );
			}

			if ( ! empty( $data['minutes_url'] ) ) {
				update_post_meta( $post_id, 'edbs_minutes_url', esc_url_raw( trim( $data['minutes_url'] ) ) );
			}

			$not_held = strtolower( trim( $data['not_held'] ?? '' ) );
			update_post_meta( $post_id, 'edbs_meeting_not_held', in_array( $not_held, [ 'yes', '1', 'true' ], true ) ? '1' : '' );

			/**
			 * Fires after a CSV row's core fields have been saved to a newly
			 * imported meeting post. A plugin extending the importer (e.g.
			 * Pro) reads its own columns off $data here and saves its own
			 * post meta / taxonomy terms.
			 *
			 * @since 1.1.0
			 *
			 * @param int   $post_id The newly created meeting post ID.
			 * @param array $data    The raw CSV row, keyed by lowercased column header. Values are trimmed but not sanitized — callbacks must sanitize before storing.
			 */
			do_action( 'edbs_csv_import_row_meta', $post_id, $data );

			++$imported;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return [
			'imported' => $imported,
			'skipped'  => $skipped,
		];
	}

	/**
	 * Renders the import admin page.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$edbs_status_message = $this->resolve_status_message();
		if ( $edbs_status_message ) {
			$this->announce_status_message( $edbs_status_message['message'], $edbs_status_message['type'] );
		}

		require EDBS_DIR . 'partials/csv-import-page.php';
	}

	/**
	 * Resolves this request's import status message (success or error) from
	 * the redirect's own query args - the single source of truth for both
	 * the visible notice markup (partials/csv-import-page.php) and the
	 * screen-reader announcement (announce_status_message()), so the two
	 * texts can never drift apart.
	 *
	 * @since x.x.x
	 *
	 * @return array{message: string, type: 'success'|'error'}|null Null when
	 *         the request carries neither query arg.
	 */
	private function resolve_status_message(): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only query args set by this plugin's own redirect, not user-submitted form data.
		if ( isset( $_GET['edbs_import_success'] ) ) {
			return [
				'type'    => 'success',
				'message' => sprintf(
					/* translators: 1: number imported, 2: number skipped */
					__( 'Import complete. %1$d rows imported, %2$d skipped.', 'boardscribe' ),
					absint( $_GET['edbs_import_success'] ),
					absint( $_GET['edbs_import_skipped'] ?? 0 )
				),
			];
		}

		if ( isset( $_GET['edbs_import_error'] ) ) {
			$messages = [
				'no_file'      => __( 'No file was uploaded. Please choose a CSV file and try again.', 'boardscribe' ),
				'invalid_type' => __( 'Invalid file type. Please upload a .csv file.', 'boardscribe' ),
			];
			// sanitize_key() calls strtolower() internally, which is a
			// TypeError in PHP 8+ if $_GET['edbs_import_error'] is an array
			// (e.g. a URL crafted/edited to ?edbs_import_error[]=x) - only
			// pass it a string, falling back to an empty (non-matching) code
			// otherwise so the generic "unknown error" message below still
			// applies rather than fataling the page.
			$raw_code = $_GET['edbs_import_error']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- sanitized (or discarded entirely) immediately below; only kept as its own variable so the is_string() guard can run before sanitize_key() ever sees it.
			$code     = is_string( $raw_code ) ? sanitize_key( wp_unslash( $raw_code ) ) : '';

			return [
				'type'    => 'error',
				'message' => $messages[ $code ] ?? __( 'An unknown error occurred.', 'boardscribe' ),
			];
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return null;
	}

	/**
	 * Announces the import status message to screen readers via
	 * wp.a11y.speak(), which pushes text into WP core's own pre-existing
	 * (initially empty) footer live region after the page has already
	 * loaded (PRO-1325 / WCAG 4.1.3).
	 *
	 * The visible notice markup alone isn't reliably announced: it's
	 * rendered as part of the initial full-page-reload response (this
	 * import flow is a classic POST -> redirect -> GET, not an AJAX
	 * update), and most screen readers only announce live-region content
	 * that *changes* after the page has already loaded - content already
	 * present at the first render isn't treated as a change. Deliberately
	 * does NOT also mark that notice `<div>` itself as a live region
	 * (e.g. role="status") as a "belt and suspenders" fix: WP core's own
	 * dismissible-notice JS (common.js) injects a "Dismiss this notice"
	 * button into any `.is-dismissible` notice shortly after page load -
	 * a real DOM mutation inside the element - which risks a second,
	 * unrelated announcement on some screen reader/browser combinations if
	 * that element were also a live region. speak() into a separate,
	 * dedicated region avoids that entirely.
	 *
	 * The speak() call itself is wrapped in wp.domReady() - wp-a11y's own
	 * live-region container elements (#a11y-speak-polite/-assertive,
	 * speak() writes into whichever it finds by that id) are created by its
	 * setup() function, which wp-a11y registers via its own internal
	 * domReady() call rather than running eagerly on script execution.
	 * Without this wrapper, an inline script placed right after the wp-a11y
	 * handle can run before that setup has fired - speak() finds no
	 * container element yet and silently no-ops, losing the announcement
	 * with no error to indicate why.
	 *
	 * @since x.x.x
	 *
	 * @param string $message The plain-text message to announce.
	 * @param string $type    'success' or 'error' - an error is announced
	 *                        assertively (interrupts, per its higher
	 *                        urgency), success politely (waits its turn),
	 *                        matching wp.a11y.speak()'s own two politeness
	 *                        levels.
	 * @return void
	 */
	private function announce_status_message( string $message, string $type ): void {
		wp_enqueue_script( 'wp-a11y' );
		wp_add_inline_script(
			'wp-a11y',
			sprintf(
				'wp.domReady( function() { wp.a11y.speak( %s, %s ); } );',
				wp_json_encode( $message ),
				wp_json_encode( 'error' === $type ? 'assertive' : 'polite' )
			)
		);
	}

	/**
	 * Parses a publish_date cell value into local/GMT post_date strings.
	 *
	 * Accepts any date/time format PHP's date parser understands — a bare
	 * date, date+time, ISO 8601 with or without a UTC offset, or a natural
	 * string like "March 15, 2024 2:30pm" — rather than one fixed format.
	 * When the value carries an explicit offset/timezone (e.g. a trailing
	 * "Z" or "-05:00"), that offset is authoritative for the real moment in
	 * time: the GMT value reflects true UTC, and the local value is that
	 * same instant converted into the site's configured timezone (which can
	 * shift the calendar date/time away from what was typed). When the
	 * value has no offset, it's treated as already being in the site's
	 * timezone, matching how a bare date/time is understood elsewhere in
	 * this importer.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Raw, already-trimmed/sanitized cell value.
	 * @return array{ local: string, gmt: string }|null Null if unparseable.
	 */
	private function parse_publish_date( string $value ): ?array {
		try {
			$date_obj = new \DateTime( $value, wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}

		// PHP's date parser silently rolls invalid-but-numeric dates
		// (e.g. "2024-02-30") forward to the next valid date rather than
		// throwing - getLastErrors() is the only way to detect that this
		// happened, so treat it the same as an unparseable value.
		$errors = \DateTime::getLastErrors();
		if ( $errors && $errors['warning_count'] > 0 ) {
			return null;
		}

		$gmt = clone $date_obj;
		$gmt->setTimezone( new \DateTimeZone( 'UTC' ) );

		$local = clone $date_obj;
		$local->setTimezone( wp_timezone() );

		return [
			'local' => $local->format( 'Y-m-d H:i:s' ),
			'gmt'   => $gmt->format( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Returns the URL of the import admin page.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function get_page_url(): string {
		return add_query_arg(
			[
				'post_type' => 'edbs_meeting',
				'page'      => 'edbs-settings',
				'tab'       => 'import',
			],
			admin_url( 'edit.php' )
		);
	}
}
