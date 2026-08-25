<?php
/**
 * Meeting Details meta box fields markup.
 *
 * @package EqualizeDigital\BoardScribe
 *
 * @var string   $meeting_date        Meeting date meta value.
 * @var string   $agenda_url  Agenda URL meta value.
 * @var string   $minutes_url Minutes URL meta value.
 * @var string   $meeting_not_held    Whether the meeting was not held.
 * @var \WP_Post $post                The current post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<table class="form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="edbs_meeting_date"><?php esc_html_e( 'Meeting Date', 'boardscribe' ); ?> <span aria-hidden="true">*</span></label>
			</th>
			<td>
				<input
					type="date"
					id="edbs_meeting_date"
					name="edbs_meeting_date"
					value="<?php echo esc_attr( $meeting_date ); ?>"
					required
					class="regular-text"
				/>
				<p class="description"><?php esc_html_e( 'Required. Select the date the meeting was or will be held.', 'boardscribe' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="edbs_agenda_url"><?php esc_html_e( 'Agenda URL', 'boardscribe' ); ?></label>
			</th>
			<td>
				<input
					type="url"
					id="edbs_agenda_url"
					name="edbs_agenda_url"
					value="<?php echo esc_url( $agenda_url ); ?>"
					class="large-text"
					placeholder="https://"
				/>
				<button
					type="button"
					class="button edbs-media-button"
					data-target="edbs_agenda_url"
					data-title="<?php esc_attr_e( 'Choose Agenda File', 'boardscribe' ); ?>"
					data-insert="<?php esc_attr_e( 'Use this file', 'boardscribe' ); ?>"
				><?php esc_html_e( 'Media Library', 'boardscribe' ); ?></button>
			</td>
		</tr>
		<?php
		/**
		 * Fires immediately after the Agenda URL field's row. Pro plugin uses
		 * this to place its Agenda Document picker directly under the URL
		 * field it takes priority over, rather than at the end of the box
		 * with the generic edbs_meta_fields hook.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edbs_after_agenda_url_field', $post );
		?>
		<tr>
			<th scope="row">
				<label for="edbs_minutes_url"><?php esc_html_e( 'Minutes URL', 'boardscribe' ); ?></label>
			</th>
			<td>
				<input
					type="url"
					id="edbs_minutes_url"
					name="edbs_minutes_url"
					value="<?php echo esc_url( $minutes_url ); ?>"
					class="large-text"
					placeholder="https://"
				/>
				<button
					type="button"
					class="button edbs-media-button"
					data-target="edbs_minutes_url"
					data-title="<?php esc_attr_e( 'Choose Minutes File', 'boardscribe' ); ?>"
					data-insert="<?php esc_attr_e( 'Use this file', 'boardscribe' ); ?>"
				><?php esc_html_e( 'Media Library', 'boardscribe' ); ?></button>
			</td>
		</tr>
		<?php
		/**
		 * Fires immediately after the Minutes URL field's row. Pro plugin
		 * uses this to place its Minutes Document picker directly under the
		 * URL field it takes priority over, rather than at the end of the
		 * box with the generic edbs_meta_fields hook.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edbs_after_minutes_url_field', $post );
		?>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Meeting Not Held', 'boardscribe' ); ?>
			</th>
			<td>
				<label for="edbs_meeting_not_held">
					<input
						type="checkbox"
						id="edbs_meeting_not_held"
						name="edbs_meeting_not_held"
						value="1"
						<?php checked( '1', $meeting_not_held ); ?>
					/>
					<?php esc_html_e( 'This meeting was not held', 'boardscribe' ); ?>
				</label>
			</td>
		</tr>
		<?php
		/**
		 * Fires after the default meta box fields are rendered. Pro plugin can
		 * use this to append additional fields.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edbs_meta_fields', $post );
		?>
	</tbody>
</table>
