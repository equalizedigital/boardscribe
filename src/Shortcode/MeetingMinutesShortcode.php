<?php
/**
 * Meeting Minutes shortcode registration and asset enqueuing.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\Shortcode;


/**
 * Registers the [edmm_meeting_minutes] shortcode and manages
 * conditional asset enqueuing.
 */
class MeetingMinutesShortcode {

	/**
	 * Whether wp_localize_script has already been called this page load.
	 *
	 * @var bool
	 */
	private static bool $localized = false;

	/**
	 * Incrementing counter used to generate unique instance IDs.
	 *
	 * @var int
	 */
	private static int $instance_count = 0;

	/**
	 * Hooks shortcode and asset enqueuing into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'edmm_meeting_minutes', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
	}

	/**
	 * Enqueues assets early (wp_enqueue_scripts) when the shortcode is
	 * detected in the current post content. This is the preferred path.
	 * Page builders that render shortcodes late will be handled by the
	 * fallback enqueue inside render().
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'edmm_meeting_minutes' ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Registers and enqueues the plugin stylesheet and script.
	 * Safe to call multiple times — WordPress deduplicates by handle.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style(
			'edmm-meeting-minutes',
			EDMM_URL . 'assets/css/meeting-minutes.css',
			[],
			EDMM_VERSION
		);

		wp_enqueue_script(
			'edmm-meeting-minutes',
			EDMM_URL . 'assets/js/meeting-minutes.js',
			[],
			EDMM_VERSION,
			true // Load in footer.
		);

		$this->localize_script();

		/**
		 * Fires after the plugin assets are enqueued.
		 * Pro plugin can enqueue its own assets here.
		 */
		do_action( 'edmm_enqueue_assets' );
	}

	/**
	 * Calls wp_localize_script once per page load to pass global
	 * config and i18n strings to the JavaScript.
	 *
	 * @return void
	 */
	private function localize_script(): void {
		if ( self::$localized ) {
			return;
		}

		wp_localize_script( 'edmm-meeting-minutes', 'edmmConfig', [
			'apiUrl' => rest_url( 'edmm/v1/meeting-minutes/' ),
			'i18n'   => [
				'colTitle'        => __( 'Title', 'edmm' ),
				'colDate'         => __( 'Date', 'edmm' ),
				'colAgenda'       => __( 'Agenda', 'edmm' ),
				'colMinutes'        => __( 'Minutes', 'edmm' ),
				'previous'        => __( 'Previous', 'edmm' ),
				'next'            => __( 'Next', 'edmm' ),
				'previousPage'    => __( 'Previous Page', 'edmm' ),
				'nextPage'        => __( 'Next Page', 'edmm' ),
				'pagination'      => __( 'Pagination', 'edmm' ),
				/* translators: %s: page number */
				'pageNum'         => __( 'Page %s', 'edmm' ),
				/* translators: 1: first entry number, 2: last entry number, 3: total entries */
				'showingEntries'  => __( 'Showing %1$s to %2$s of %3$s entries', 'edmm' ),
			],
		] );

		self::$localized = true;
	}

	/**
	 * Renders the [edmm_meeting_minutes] shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts ): string {
		$defaults = [
			'included_years'       => '',
			'hide_title'           => 'false',
			'hide_date'            => 'false',
			'hide_agenda'          => 'false',
			'hide_minutes'           => 'false',
			'title_label'          => '',
			'date_label'           => '',
			'agenda_label'         => '',
			'minutes_label'          => '',
			'agenda_link_label'    => '',
			'minutes_link_label'     => '',
			'held_date_format'     => 'Y/m/d',
			'not_held_date_format' => 'Y/m',
			'class'                => '',
			'posts_per_page'       => 20,
		];

		/**
		 * Filters the recognized shortcode attribute defaults.
		 * Pro plugin can add additional attributes (e.g., category).
		 *
		 * @param array $defaults The default attribute values.
		 */
		$defaults = apply_filters( 'edmm_shortcode_atts', $defaults );

		$atts = shortcode_atts( $defaults, $atts, 'edmm_meeting_minutes' );

		// Ensure assets are enqueued even when page builders call shortcodes late.
		$this->enqueue_assets();

		// Generate a unique instance ID for this shortcode invocation.
		self::$instance_count++;
		$instance_id = 'edmm_' . self::$instance_count;

		$instance_config = [
			'instanceId'        => $instance_id,
			'includedYears'     => $atts['included_years'],
			'hideTitle'         => filter_var( $atts['hide_title'], FILTER_VALIDATE_BOOLEAN ),
			'hideDate'          => filter_var( $atts['hide_date'], FILTER_VALIDATE_BOOLEAN ),
			'hideAgenda'        => filter_var( $atts['hide_agenda'], FILTER_VALIDATE_BOOLEAN ),
			'hideMinutes'         => filter_var( $atts['hide_minutes'], FILTER_VALIDATE_BOOLEAN ),
			'titleLabel'        => sanitize_text_field( $atts['title_label'] ),
			'dateLabel'         => sanitize_text_field( $atts['date_label'] ),
			'agendaLabel'       => sanitize_text_field( $atts['agenda_label'] ),
			'minutesLabel'        => sanitize_text_field( $atts['minutes_label'] ),
			'agendaLinkLabel'   => sanitize_text_field( $atts['agenda_link_label'] ),
			'minutesLinkLabel'    => sanitize_text_field( $atts['minutes_link_label'] ),
			'category'          => sanitize_text_field( $atts['category'] ?? '' ),
			'heldDateFormat'    => $atts['held_date_format'],
			'notHeldDateFormat' => $atts['not_held_date_format'],
			'tableClass'        => $atts['class'],
			'postsPerPage'      => absint( $atts['posts_per_page'] ),
		];

		ob_start();
		?>
		<div
			class="edmm-meeting-minutes-wrap"
			data-config="<?php echo esc_attr( wp_json_encode( $instance_config ) ); ?>"
		>
			<div id="edmm-table-<?php echo esc_attr( $instance_id ); ?>" class="edmm-table-container"></div>
			<div id="edmm-pagination-<?php echo esc_attr( $instance_id ); ?>" class="edmm-pagination-container"></div>
			<div
				id="edmm-info-<?php echo esc_attr( $instance_id ); ?>"
				class="edmm-pagination-info"
				aria-live="polite"
				aria-atomic="true"
				style="position: absolute; left: -9999px;"
			></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
