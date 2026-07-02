<?php
/**
 * Meeting Minutes shortcode registration and asset enqueuing.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\Shortcode;

use EqualizeDigital\MeetingMinutes\REST\MeetingMinutesEndpoint;

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

		// The bundle is built from assets/src/js/ by `npm run build` and is
		// not committed - the generated .asset.php carries a content-hash
		// version (and any dependencies) for cache busting.
		$asset_file = EDMM_DIR . 'assets/build/meeting-minutes.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [
				'dependencies' => [],
				'version'      => EDMM_VERSION,
			];

		wp_enqueue_script(
			'edmm-meeting-minutes',
			EDMM_URL . 'assets/build/meeting-minutes.js',
			$asset['dependencies'],
			$asset['version'],
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

		wp_localize_script(
			'edmm-meeting-minutes',
			'edmmConfig',
			[
				'apiUrl' => rest_url( 'edmm/v1/meeting-minutes/' ),
				'i18n'   => [
					'colTitle'       => __( 'Title', 'edmm' ),
					'colDate'        => __( 'Date', 'edmm' ),
					'colAgenda'      => __( 'Agenda', 'edmm' ),
					'colMinutes'     => __( 'Minutes', 'edmm' ),
					'previous'       => __( 'Previous', 'edmm' ),
					'next'           => __( 'Next', 'edmm' ),
					'previousPage'   => __( 'Previous Page', 'edmm' ),
					'nextPage'       => __( 'Next Page', 'edmm' ),
					'pagination'     => __( 'Pagination', 'edmm' ),
					/* translators: %s: page number */
					'pageNum'        => __( 'Page %s', 'edmm' ),
					/* translators: 1: first entry number, 2: last entry number, 3: total entries */
					'showingEntries' => __( 'Showing %1$s to %2$s of %3$s entries', 'edmm' ),
				],
			]
		);

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
			'hide_minutes'         => 'false',
			'title_label'          => '',
			'date_label'           => '',
			'agenda_label'         => '',
			'minutes_label'        => '',
			'agenda_link_label'    => '',
			'minutes_link_label'   => '',
			'held_date_format'     => 'Y/m/d',
			'not_held_date_format' => 'Y/m',
			'class'                => '',
			'posts_per_page'       => 20,
			'template'             => '',
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
		++self::$instance_count;
		$instance_id = 'edmm_' . self::$instance_count;

		$instance_config = [
			'instanceId'        => $instance_id,
			'includedYears'     => $atts['included_years'],
			'hideTitle'         => filter_var( $atts['hide_title'], FILTER_VALIDATE_BOOLEAN ),
			'hideDate'          => filter_var( $atts['hide_date'], FILTER_VALIDATE_BOOLEAN ),
			'hideAgenda'        => filter_var( $atts['hide_agenda'], FILTER_VALIDATE_BOOLEAN ),
			'hideMinutes'       => filter_var( $atts['hide_minutes'], FILTER_VALIDATE_BOOLEAN ),
			'titleLabel'        => sanitize_text_field( $atts['title_label'] ),
			'dateLabel'         => sanitize_text_field( $atts['date_label'] ),
			'agendaLabel'       => sanitize_text_field( $atts['agenda_label'] ),
			'minutesLabel'      => sanitize_text_field( $atts['minutes_label'] ),
			'agendaLinkLabel'   => sanitize_text_field( $atts['agenda_link_label'] ),
			'minutesLinkLabel'  => sanitize_text_field( $atts['minutes_link_label'] ),
			'category'          => sanitize_text_field( $atts['category'] ?? '' ),
			'heldDateFormat'    => $this->sanitize_date_format( $atts['held_date_format'], $defaults['held_date_format'] ),
			'notHeldDateFormat' => $this->sanitize_date_format( $atts['not_held_date_format'], $defaults['not_held_date_format'] ),
			'tableClass'        => $this->sanitize_class_list( $atts['class'] ),
			'postsPerPage'      => absint( $atts['posts_per_page'] ),
			// Display template name, resolved client-side against the
			// window.edmmTemplates registry; unknown/empty names fall back
			// to the built-in table template.
			'template'          => sanitize_key( $atts['template'] ),
		];

		/**
		 * Filters the per-instance config array before it is JSON-encoded into
		 * the data-config attribute. Pro plugin uses this to append its own
		 * settings (e.g. hide_location, location_label).
		 *
		 * @param array $instance_config The instance configuration array.
		 * @param array $atts            The resolved shortcode attributes.
		 */
		$instance_config = apply_filters( 'edmm_shortcode_instance_config', $instance_config, $atts );

		ob_start();
		?>
		<div
			class="edmm-meeting-minutes-wrap"
			data-config="<?php echo esc_attr( wp_json_encode( $instance_config ) ); ?>"
		>
			<?php
			/**
			 * Fires inside the shortcode wrapper, before the table container.
			 * Pro plugin can use this to render e.g. a search box, a date
			 * range picker, or an "add to calendar" button.
			 *
			 * @param string $instance_id The unique instance ID for this shortcode invocation.
			 * @param array  $atts        The resolved shortcode attributes.
			 */
			do_action( 'edmm_before_table', $instance_id, $atts );
			?>
			<div id="edmm-table-<?php echo esc_attr( $instance_id ); ?>" class="edmm-table-container"></div>
			<div id="edmm-pagination-<?php echo esc_attr( $instance_id ); ?>" class="edmm-pagination-container"></div>
			<div
				id="edmm-info-<?php echo esc_attr( $instance_id ); ?>"
				class="edmm-pagination-info"
				aria-live="polite"
				aria-atomic="true"
				style="position: absolute; left: -9999px;"
			></div>
			<?php
			/**
			 * Fires inside the shortcode wrapper, after the table container.
			 * Pro plugin can use this to render e.g. a comment/feedback form
			 * or a print button.
			 *
			 * @param string $instance_id The unique instance ID for this shortcode invocation.
			 * @param array  $atts        The resolved shortcode attributes.
			 */
			do_action( 'edmm_after_table', $instance_id, $atts );
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sanitizes a space-separated list of HTML class names.
	 *
	 * The `class` shortcode attribute is rendered client-side into the
	 * table markup's class attribute via string concatenation, so it must
	 * be restricted to safe class-name characters before it ever reaches
	 * the instance config to prevent stored XSS via the shortcode attribute.
	 *
	 * @param string $class_list Raw, space-separated class names.
	 * @return string Sanitized, space-separated class names.
	 */
	private function sanitize_class_list( string $class_list ): string {
		$classes = array_map( 'sanitize_html_class', explode( ' ', $class_list ) );
		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Sanitizes a date format shortcode attribute and falls back to the
	 * given default if it fails the same allow-list the REST endpoint
	 * enforces (MeetingMinutesEndpoint::validate_date_format()).
	 *
	 * Without this, an invalid shortcode attribute would still reach the
	 * REST fetch, get rejected with a 400, and silently fail to render
	 * the whole table client-side.
	 *
	 * @param string $value   Raw held_date_format/not_held_date_format attribute.
	 * @param string $default_value Fallback value if $value is invalid.
	 * @return string
	 */
	private function sanitize_date_format( string $value, string $default_value ): string {
		$value = sanitize_text_field( $value );
		return MeetingMinutesEndpoint::validate_date_format( $value ) ? $value : $default_value;
	}
}
