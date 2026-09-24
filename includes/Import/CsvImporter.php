<?php
/**
 * CSV importer for BoardScribe.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Import;

use EqualizeDigital\BoardScribe\Admin\MetaBox;
use EqualizeDigital\BoardScribe\Helpers\Helpers;

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
				'notes'    => __( 'Full URL to agenda file. Must be a full http:// or https:// address; an invalid URL is not saved.', 'boardscribe' ),
			],
			'minutes_url'  => [
				'required' => false,
				'notes'    => __( 'Full URL to minutes file. Must be a full http:// or https:// address; an invalid URL is not saved.', 'boardscribe' ),
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
					__( 'Any recognizable date or date/time (e.g. %s). Sets the actual WordPress publish date instead of the moment of import — use this to backdate historical meetings. Does not affect the meeting date/sort order above. A future date/time schedules the meeting instead of publishing it immediately, and it will not appear on the site until then.', 'boardscribe' ),
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified above; the whole array is handed to validate_upload() to check/sanitize, not read raw here.
		$raw_file = isset( $_FILES['edbs_csv'] ) && is_array( $_FILES['edbs_csv'] ) ? $_FILES['edbs_csv'] : [];
		$error    = $this->validate_upload( $raw_file );
		if ( null !== $error ) {
			wp_safe_redirect( add_query_arg( 'edbs_import_error', $error, $this->get_page_url() ) );
			exit;
		}

		$file = $raw_file['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value is a server-generated tmp path, validated by validate_upload() above.

		$result = $this->process_csv( $file );

		wp_safe_redirect(
			add_query_arg(
				[
					'edbs_import_success'      => $result['imported'],
					'edbs_import_skipped'      => $result['skipped'],
					'edbs_import_scheduled'    => $result['scheduled'],
					'edbs_import_duplicates'   => $result['duplicates'],
					'edbs_import_invalid_urls' => $result['invalid_urls'],
				],
				$this->get_page_url()
			)
		);
		exit;
	}

	/**
	 * Validates a $_FILES['edbs_csv']-shaped array before it's handed to
	 * process_csv(). Split out from handle_upload() (which calls exit()
	 * on every path, making it awkward to unit test directly) so this
	 * logic is directly testable.
	 *
	 * @since x.x.x
	 *
	 * @param array{tmp_name?: mixed, error?: mixed, name?: mixed} $file The $_FILES['edbs_csv'] entry, or [] if absent.
	 * @return string|null The edbs_import_error code to redirect with, or null when the upload is good to process.
	 */
	private function validate_upload( array $file ): ?string {
		// Checked before tmp_name: PHP leaves tmp_name empty for several
		// upload errors (e.g. UPLOAD_ERR_INI_SIZE, an over-large file), not
		// just "no file chosen" (UPLOAD_ERR_NO_FILE) - checking tmp_name
		// first would report every one of those as the generic "no file"
		// message instead of the more accurate "upload failed", and skip
		// the upload_failed branch below entirely.
		$upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE === $upload_error ) {
			return 'no_file';
		}
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return 'upload_failed';
		}

		// A UPLOAD_ERR_OK error code should always come with a non-empty
		// tmp_name, but don't assume it - fall back to the same "no file"
		// message rather than passing an empty path to is_uploaded_file()/
		// mime_content_type() below.
		if ( empty( $file['tmp_name'] ) ) {
			return 'no_file';
		}

		// is_uploaded_file() confirms tmp_name actually came from this
		// request's multipart upload (not, say, a stale/attacker-guessed
		// tmp path) before anything else touches it.
		if ( ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return 'invalid_type';
		}

		$mime      = mime_content_type( (string) $file['tmp_name'] );
		$extension = isset( $file['name'] ) ? strtolower( (string) pathinfo( sanitize_file_name( wp_unslash( (string) $file['name'] ) ), PATHINFO_EXTENSION ) ) : '';
		if ( ! $this->is_allowed_csv_upload( false === $mime ? '' : $mime, $extension ) ) {
			return 'invalid_type';
		}

		return null;
	}

	/**
	 * Whether a detected MIME type + filename extension pair is acceptable
	 * for a CSV upload. Pure and side-effect free (no filesystem access),
	 * kept separate from validate_upload() so it's directly unit-testable.
	 *
	 * `text/plain` stays on the MIME allow-list - many simple CSV exports
	 * are indistinguishable from plain text to mime_content_type() - but
	 * is now paired with an extension check, so a renamed-but-otherwise-
	 * plain-text non-CSV file can't ride through on text/plain alone.
	 *
	 * @since x.x.x
	 *
	 * @param string $mime      The detected MIME type (mime_content_type()'s return value, or '' if detection failed).
	 * @param string $extension The lowercased filename extension, without the leading dot.
	 * @return bool
	 */
	private function is_allowed_csv_upload( string $mime, string $extension ): bool {
		return 'csv' === $extension
			&& in_array( $mime, [ 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ], true );
	}

	/**
	 * Processes the uploaded CSV file and creates posts.
	 *
	 * @since 1.1.0
	 *
	 * @param string $file Path to the temporary uploaded file.
	 * @return array{ imported: int, skipped: int, scheduled: int, duplicates: int, invalid_urls: int }
	 */
	private function process_csv( string $file ): array {
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			return [
				'imported'     => 0,
				'skipped'      => 0,
				'scheduled'    => 0,
				'duplicates'   => 0,
				'invalid_urls' => 0,
			];
		}

		// A large CSV can comfortably outrun the default 30s/128M admin
		// request before every row is inserted, silently truncating the
		// import mid-file with no indication anything went wrong - raise
		// both the same way WordPress's own bulk operations (e.g. media
		// import) do. wp_raise_memory_limit() already checks WP_MAX_MEMORY_LIMIT
		// and only raises, never lowers; set_time_limit() is a no-op under
		// most restricted hosting (safe_mode is gone since PHP 8, but some
		// hosts disable the function entirely) - suppressed rather than
		// treated as fatal either way.
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled entirely on some hosts; failure here isn't fatal to the import.
		}

		$required_columns = array_keys( array_filter( $this->columns(), fn( array $column ): bool => ! empty( $column['required'] ) ) );
		$meta_box         = new MetaBox();

		$imported     = 0;
		$skipped      = 0;
		$scheduled    = 0;
		$duplicates   = 0;
		$invalid_urls = 0;
		$headers      = null;

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

			// Validate the optional URL columns. esc_url_raw() alone encodes a
			// malformed value into a dead link with nothing in the results to say
			// so. An invalid URL doesn't discard the meeting - it is not saved and
			// is reported, since losing a whole meeting to one bad cell is worse
			// than losing the link.
			$row_invalid_urls = [];
			foreach ( [ 'agenda_url', 'minutes_url' ] as $url_column ) {
				if ( isset( $data[ $url_column ] ) && '' !== trim( (string) $data[ $url_column ] ) && ! Helpers::is_valid_external_url( trim( (string) $data[ $url_column ] ) ) ) {
					$row_invalid_urls[] = $url_column;
				}
			}

			$title = sanitize_text_field( trim( $data['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = $meta_box->generate_default_title( $date );
			}

			if ( $this->find_existing_meeting( $title, $date ) ) {
				++$duplicates;
				continue;
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

			// wp_insert_post() silently downgrades a 'publish' status to
			// 'future' when post_date is later than now (normal WP
			// scheduling semantics) - that's a real, intentional path here
			// (backdating uses the same publish_date column for the past),
			// but a scheduled meeting won't appear on the site or in "N
			// rows imported" until cron publishes it, so it's counted and
			// reported separately rather than folded into $imported.
			if ( 'future' === get_post_status( $post_id ) ) {
				++$scheduled;
			} else {
				++$imported;
			}

			if ( ! empty( $data['agenda_url'] ) && ! in_array( 'agenda_url', $row_invalid_urls, true ) ) {
				update_post_meta( $post_id, 'edbs_agenda_url', esc_url_raw( trim( $data['agenda_url'] ) ) );
			}

			if ( ! empty( $data['minutes_url'] ) && ! in_array( 'minutes_url', $row_invalid_urls, true ) ) {
				update_post_meta( $post_id, 'edbs_minutes_url', esc_url_raw( trim( $data['minutes_url'] ) ) );
			}

			if ( $row_invalid_urls ) {
				++$invalid_urls;
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
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return [
			'imported'     => $imported,
			'skipped'      => $skipped,
			'scheduled'    => $scheduled,
			'duplicates'   => $duplicates,
			'invalid_urls' => $invalid_urls,
		];
	}

	/**
	 * Whether a meeting with this exact title and edbs_meeting_date already
	 * exists (any status except trash/auto-draft, so a meeting you trashed is
	 * re-created on re-import; the comparison is case-insensitive under the
	 * usual MySQL collation), so re-uploading the same CSV - e.g. after a
	 * timed-out import stopped partway through - doesn't duplicate every row
	 * that already landed. Deliberately not scoped to a "recently imported"
	 * window or an import-run hash: a plain, human-editable CSV has no
	 * stable per-row identity to hash beyond its own visible columns, and
	 * (title, date) is already the pair a person re-running the same file
	 * would recognise as "the same meeting".
	 *
	 * @since x.x.x
	 *
	 * @param string $title The row's resolved title (after the blank-title fallback).
	 * @param string $date  The row's edbs_meeting_date value (Y-m-d).
	 * @return bool
	 */
	private function find_existing_meeting( string $title, string $date ): bool {
		$existing = get_posts(
			[
				'post_type'      => 'edbs_meeting',
				'post_status'    => 'any',
				// The title as wp_insert_post() will have stored it (kses/entity
				// encoding, unslashing) - a raw "Parks & Rec" wouldn't otherwise
				// match a stored "Parks &amp; Rec" on sites that filter titles.
				'title'          => wp_unslash( sanitize_post_field( 'post_title', wp_slash( $title ), 0, 'db' ) ),
				'meta_key'       => 'edbs_meeting_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $date, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			]
		);

		return ! empty( $existing );
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
			$scheduled    = absint( $_GET['edbs_import_scheduled'] ?? 0 );
			$duplicates   = absint( $_GET['edbs_import_duplicates'] ?? 0 );
			$invalid_urls = absint( $_GET['edbs_import_invalid_urls'] ?? 0 );

			$message = sprintf(
				/* translators: 1: number imported, 2: number skipped */
				__( 'Import complete. %1$d rows imported, %2$d skipped.', 'boardscribe' ),
				absint( $_GET['edbs_import_success'] ),
				absint( $_GET['edbs_import_skipped'] ?? 0 )
			);

			// Scheduled and duplicate rows have their own counters (they
			// aren't invalid, so not part of "skipped") and need their own
			// explanation - appended only when non-zero so a plain import
			// keeps its original, shorter message.
			if ( $scheduled > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of rows scheduled for a future date */
					_n( '%d row was scheduled for a future date and will not appear until then.', '%d rows were scheduled for a future date and will not appear until then.', $scheduled, 'boardscribe' ),
					$scheduled
				);
			}

			if ( $duplicates > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of duplicate rows skipped */
					_n( '%d row matched an existing meeting, or an earlier row in this file, with the same title and date and was skipped.', '%d rows matched an existing meeting, or an earlier row in this file, with the same title and date and were skipped.', $duplicates, 'boardscribe' ),
					$duplicates
				);
			}

			if ( $invalid_urls > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of rows whose agenda/minutes URL was invalid */
					_n( '%d row had an invalid agenda or minutes URL; that URL was not saved.', '%d rows had an invalid agenda or minutes URL; those URLs were not saved.', $invalid_urls, 'boardscribe' ),
					$invalid_urls
				);
			}

			return [
				'type'    => 'success',
				'message' => $message,
			];
		}

		if ( isset( $_GET['edbs_import_error'] ) ) {
			$messages = [
				'no_file'       => __( 'No file was uploaded. Please choose a CSV file and try again.', 'boardscribe' ),
				'invalid_type'  => __( 'Invalid file type. Please upload a .csv file.', 'boardscribe' ),
				'upload_failed' => __( 'The file failed to upload completely. Please try again.', 'boardscribe' ),
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
