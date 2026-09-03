<?php
/**
 * CSV importer for BoardScribe.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Import;

/**
 * Provides a bulk CSV import admin page under the BoardScribe menu.
 *
 * Core recognised columns (see columns()): title, date, agenda_url,
 * minutes_url, not_held, publish_date.
 *
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
				'required' => true,
				'notes'    => __( 'Meeting title', 'boardscribe' ),
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
				'notes'    => __( 'yes / 1 / true', 'boardscribe' ),
			],
			'publish_date' => [
				'required' => false,
				'notes'    => __( 'Any recognizable date or date/time (e.g. 2024-03-15, 2024-03-15 14:30:00, or 2024-03-15T14:30:00-05:00). Sets the actual WordPress publish date instead of the moment of import — use this to backdate historical meetings. Does not affect the meeting date/sort order above.', 'boardscribe' ),
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

			$post_args = [
				'post_title'  => sanitize_text_field( trim( $data['title'] ) ),
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

		require EDBS_DIR . 'partials/csv-import-page.php';
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
