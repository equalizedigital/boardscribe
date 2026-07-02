<?php
/**
 * Meeting Details meta box fields markup.
 *
 * @package EqualizeDigital\MeetingMinutes
 *
 * @var string   $meeting_date        Meeting date meta value.
 * @var string   $meeting_agenda_url  Agenda URL meta value.
 * @var string   $meeting_minutes_url Minutes URL meta value.
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
				<label for="edmm_meeting_date"><?php esc_html_e( 'Meeting Date', 'edmm' ); ?> <span aria-hidden="true">*</span></label>
			</th>
			<td>
				<input
					type="date"
					id="edmm_meeting_date"
					name="edmm_meeting_date"
					value="<?php echo esc_attr( $meeting_date ); ?>"
					required
					class="regular-text"
				/>
				<p class="description"><?php esc_html_e( 'Required. Format: YYYY-MM-DD', 'edmm' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="edmm_meeting_agenda_url"><?php esc_html_e( 'Agenda URL', 'edmm' ); ?></label>
			</th>
			<td>
				<input
					type="url"
					id="edmm_meeting_agenda_url"
					name="edmm_meeting_agenda_url"
					value="<?php echo esc_url( $meeting_agenda_url ); ?>"
					class="large-text"
					placeholder="https://"
				/>
				<button
					type="button"
					class="button edmm-media-button"
					data-target="edmm_meeting_agenda_url"
					data-title="<?php esc_attr_e( 'Choose Agenda File', 'edmm' ); ?>"
					data-insert="<?php esc_attr_e( 'Use this file', 'edmm' ); ?>"
				><?php esc_html_e( 'Media Library', 'edmm' ); ?></button>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="edmm_meeting_minutes_url"><?php esc_html_e( 'Minutes URL', 'edmm' ); ?></label>
			</th>
			<td>
				<input
					type="url"
					id="edmm_meeting_minutes_url"
					name="edmm_meeting_minutes_url"
					value="<?php echo esc_url( $meeting_minutes_url ); ?>"
					class="large-text"
					placeholder="https://"
				/>
				<button
					type="button"
					class="button edmm-media-button"
					data-target="edmm_meeting_minutes_url"
					data-title="<?php esc_attr_e( 'Choose Minutes File', 'edmm' ); ?>"
					data-insert="<?php esc_attr_e( 'Use this file', 'edmm' ); ?>"
				><?php esc_html_e( 'Media Library', 'edmm' ); ?></button>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Meeting Not Held', 'edmm' ); ?>
			</th>
			<td>
				<label for="edmm_meeting_not_held">
					<input
						type="checkbox"
						id="edmm_meeting_not_held"
						name="edmm_meeting_not_held"
						value="1"
						<?php checked( '1', $meeting_not_held ); ?>
					/>
					<?php esc_html_e( 'This meeting was not held', 'edmm' ); ?>
				</label>
			</td>
		</tr>
		<?php
		/**
		 * Fires after the default meta box fields are rendered. Pro plugin can
		 * use this to append additional fields.
		 *
		 * @since x.x.x
		 *
		 * @param \WP_Post $post The current post.
		 */
		do_action( 'edmm_meta_fields', $post );
		?>
	</tbody>
</table>
