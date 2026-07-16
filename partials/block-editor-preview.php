<?php
/**
 * Block editor preview markup for the Meeting Minutes block.
 *
 * Renders one static table over a prepared column/row set. Required by
 * BoardScribeBlock::render_preview_table(), possibly more than once
 * per preview (e.g. Pro's year-timeline renders it per year group).
 *
 * @package EqualizeDigital\BoardScribe
 *
 * @var array $visible_columns Column definitions ({label, render_cell}), hidden ones already removed.
 * @var array $rows            List of { post: \WP_Post, row: array } entries; row is build_meeting_row() output.
 * @var bool  $equal_columns   Whether to force all columns to the same width.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table
	class="edbs-meeting-minutes-table widefat"
	style="border-collapse:collapse;<?php echo $equal_columns ? ' table-layout:fixed;' : ''; ?>"
>
	<thead>
		<tr>
			<?php foreach ( $visible_columns as $edbs_column ) : ?>
				<th scope="col" style="padding:8px 12px;"><?php echo esc_html( $edbs_column['label'] ?? '' ); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
		<?php if ( $rows ) : ?>
			<?php foreach ( $rows as $edbs_entry ) : ?>
				<tr style="border-top:1px solid #ddd;">
					<?php foreach ( $visible_columns as $edbs_column ) : ?>
						<td style="padding:8px 12px;">
							<?php
							// render_cell() output is pre-escaped HTML by documented
							// contract (see the edbs_block_preview_columns filter) -
							// the same trust contract as the front end's
							// window.edbsExtraColumns renderCell().
							echo call_user_func( $edbs_column['render_cell'], $edbs_entry['row'], $edbs_entry['post'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td
					colspan="<?php echo esc_attr( count( $visible_columns ) ); ?>"
					style="padding:16px 12px; color:#757575; font-style:italic;"
				>
					<?php esc_html_e( 'No published meeting minutes found.', 'boardscribe' ); ?>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
