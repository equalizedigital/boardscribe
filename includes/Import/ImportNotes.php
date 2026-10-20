<?php
/**
 * Counts extra per-row results an add-on reports during a CSV import.
 *
 * @package EqualizeDigital\BoardScribe
 */

namespace EqualizeDigital\BoardScribe\Import;

/**
 * Passed as the third argument of edbs_csv_import_row_meta. An add-on calls
 * add() for something worth telling the user about (for example a linked
 * document that was skipped) and registers the wording for that key on
 * edbs_csv_import_notes, so the importer can add it to the results notice.
 *
 * @since 1.2.0
 */
class ImportNotes {

	/**
	 * Running totals, keyed by note key.
	 *
	 * @var array<string, int>
	 */
	private array $counts = [];

	/**
	 * Adds to a note's total.
	 *
	 * @since 1.2.0
	 *
	 * @param string $key Note key; sanitized with sanitize_key().
	 * @param int    $by  How many to add. Values below 1 are ignored.
	 * @return void
	 */
	public function add( string $key, int $by = 1 ): void {
		$key = sanitize_key( $key );

		if ( '' === $key || $by < 1 ) {
			return;
		}

		$this->counts[ $key ] = ( $this->counts[ $key ] ?? 0 ) + $by;
	}

	/**
	 * Returns every note total.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, int>
	 */
	public function all(): array {
		return $this->counts;
	}
}
