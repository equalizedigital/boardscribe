<?php
/**
 * CSV import admin page markup.
 *
 * @package EqualizeDigital\BoardScribe
 *
 * @var \EqualizeDigital\BoardScribe\Import\CsvImporter $this Importer instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="edbs-import">
	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only query args set by this plugin's own redirect, not user-submitted form data. ?>
	<?php if ( isset( $_GET['edbs_import_success'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: 1: number imported, 2: number skipped */
					esc_html__( 'Import complete. %1$d rows imported, %2$d skipped.', 'boardscribe' ),
					absint( $_GET['edbs_import_success'] ),
					absint( $_GET['edbs_import_skipped'] ?? 0 )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['edbs_import_error'] ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php
				$edbs_error_messages = [
					'no_file'      => __( 'No file was uploaded. Please choose a CSV file and try again.', 'boardscribe' ),
					'invalid_type' => __( 'Invalid file type. Please upload a .csv file.', 'boardscribe' ),
				];
				$edbs_error_code     = sanitize_key( $_GET['edbs_import_error'] );
				echo esc_html( $edbs_error_messages[ $edbs_error_code ] ?? __( 'An unknown error occurred.', 'boardscribe' ) );
				?>
			</p>
		</div>
	<?php endif; ?>
	<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

	<p><?php esc_html_e( 'Upload a CSV file to bulk-import meetings. The first row must be a header row.', 'boardscribe' ); ?></p>

	<h2><?php esc_html_e( 'CSV Format', 'boardscribe' ); ?></h2>
	<table class="widefat striped" style="max-width:600px; margin-bottom:24px;">
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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'edbs_csv_import', 'edbs_csv_import_nonce' ); ?>
		<input type="hidden" name="action" value="edbs_csv_import" />
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="edbs_csv"><?php esc_html_e( 'CSV File', 'boardscribe' ); ?></label>
					</th>
					<td>
						<input type="file" id="edbs_csv" name="edbs_csv" accept=".csv,text/csv" required />
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Import', 'boardscribe' ) ); ?>
	</form>
</div>
