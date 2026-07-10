<?php
/**
 * Meeting Minutes shortcode registration and asset enqueuing.
 *
 * @package EqualizeDigital\MeetingMinutes
 */

namespace EqualizeDigital\MeetingMinutes\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'edmm_meeting_minutes', [ $this, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
	}

	/**
	 * Enqueues assets early when the shortcode is detected in the current post
	 * content. Page builders that render shortcodes late are handled by the
	 * fallback enqueue inside render().
	 *
	 * @since x.x.x
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
	 * @since x.x.x
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

		// The bundle is built from src/js/ by `npm run build` and is
		// not committed. @wordpress/* imports resolve to wp.* globals via
		// the externals map in webpack.config.js — this dependency list is
		// maintained by hand and must stay in sync with that map.
		wp_enqueue_script(
			'edmm-meeting-minutes',
			EDMM_URL . 'assets/build/meeting-minutes.js',
			[ 'wp-escape-html', 'wp-i18n' ],
			EDMM_VERSION,
			true // Load in footer.
		);

		// Load the JS translation files (JSON) for __() calls in the bundle.
		wp_set_script_translations( 'edmm-meeting-minutes', 'edmm' );

		$this->localize_script();

		/**
		 * Fires after the plugin assets are enqueued.
		 * Pro plugin can enqueue its own assets here.
		 *
		 * @since x.x.x
		 */
		do_action( 'edmm_enqueue_assets' );
	}

	/**
	 * Calls wp_localize_script once per page load to pass global
	 * config and i18n strings to the JavaScript.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $atts ): string {
		$fields = FieldRegistry::all();

		$defaults = [];
		foreach ( $fields as $field ) {
			$defaults[ $field['key'] ] = $field['default'] ?? '';
		}

		$atts = shortcode_atts( $defaults, $atts, 'edmm_meeting_minutes' );

		// Ensure assets are enqueued even when page builders call shortcodes late.
		$this->enqueue_assets();

		// Generate a unique instance ID for this shortcode invocation.
		++self::$instance_count;
		$instance_id = 'edmm_' . self::$instance_count;

		// Every recognized attribute (free's own plus anything Pro/third
		// parties added via edmm_shortcode_field_registry) is resolved into
		// the instance config here, by type — see FieldRegistry::resolve_value().
		// Display template name resolves client-side against the
		// window.edmmTemplates registry; unknown/empty names fall back to
		// the built-in table template.
		$instance_config = [ 'instanceId' => $instance_id ];
		foreach ( $fields as $field ) {
			$instance_config[ FieldRegistry::config_key( $field ) ] = FieldRegistry::resolve_value( $field, $atts[ $field['key'] ] );
		}

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
			 * @since x.x.x
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
			 * @since x.x.x
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
}
