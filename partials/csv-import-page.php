<?php
/**
 * CSV import admin page markup.
 *
 * @package EqualizeDigital\BoardScribe
 *
 * @var \EqualizeDigital\BoardScribe\Import\CsvImporter    $this                 Importer instance.
 * @var array{message: string, type: 'success'|'error'}|null $edbs_status_message This request's import result, resolved (and already announced to screen readers) by render_page() - null when this isn't a post-import page load.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="edbs-import">
	<?php if ( $edbs_status_message ) : ?>
		<div class="notice notice-<?php echo esc_attr( $edbs_status_message['type'] ); ?><?php echo 'success' === $edbs_status_message['type'] ? ' is-dismissible' : ''; ?>">
			<p><?php echo esc_html( $edbs_status_message['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<p><?php esc_html_e( 'Upload a CSV file to bulk-import meetings.', 'boardscribe' ); ?></p>

	<h2><?php esc_html_e( 'CSV Format', 'boardscribe' ); ?></h2>
	<table class="widefat striped edbs-import__format-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Column', 'boardscribe' ); ?></th>
				<th><?php esc_html_e( 'Required', 'boardscribe' ); ?></th>
				<th><?php esc_html_e( 'Notes', 'boardscribe' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $this->columns() as $edbs_column_key => $edbs_column ) : ?>
				<tr>
					<td><code><?php echo esc_html( $edbs_column_key ); ?></code></td>
					<td><?php echo $edbs_column['required'] ? esc_html__( 'Yes', 'boardscribe' ) : esc_html__( 'No', 'boardscribe' ); ?></td>
					<td><?php echo esc_html( $edbs_column['notes'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" aria-labelledby="edbs-import-heading" class="edbs-import__upload">
		<?php wp_nonce_field( 'edbs_csv_import', 'edbs_csv_import_nonce' ); ?>
		<input type="hidden" name="action" value="edbs_csv_import" />
		<div class="edbs-import__upload-header">
			<h2 id="edbs-import-heading" class="edbs-import__upload-title"><?php esc_html_e( 'Upload CSV', 'boardscribe' ); ?></h2>
			<p class="edbs-import__upload-description"><?php esc_html_e( 'Choose a file using the columns above. The first row must be a header row, and each valid row after it creates one meeting.', 'boardscribe' ); ?></p>
		</div>
		<div class="edbs-import__file-upload">
			<label for="edbs_csv" class="edbs-import__file-label"><?php esc_html_e( 'CSV file', 'boardscribe' ); ?></label>
			<input type="file" id="edbs_csv" name="edbs_csv" accept=".csv,text/csv" required />
		</div>
		<div class="edbs-import__actions">
			<?php submit_button( __( 'Import Meetings', 'boardscribe' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
