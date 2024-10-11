<?php
namespace EqualizeDigital\MeetingMinutes;

/**
 * Plugin Name: Equalize Digital Meeting Minutes
 * Text Domain: edmm
 * Description: Manage and display meeting minutes as a custom post type with meta fields.
 * Version: 1.0.0
 * Author: Equalize Digital
 * Author URI: https://equalizedigital.com
 */

// Register Custom Post Type
function register_meeting_minutes_cpt() {
    $labels = array(
        'name'               => _x( 'Meeting Minutes', 'Post Type General Name', 'edmm' ),
        'singular_name'      => _x( 'Meeting Minute', 'Post Type Singular Name', 'edmm' ),
        'menu_name'          => __( 'Meeting Minutes', 'edmm' ),
        'name_admin_bar'     => __( 'Meeting Minute', 'edmm' ),
    );

    $args = array(
        'label'              => __( 'Meeting Minute', 'edmm' ),
        'labels'             => $labels,
        'supports'           => array( 'title', 'editor' ),
        'public'             => false,
        'has_archive'        => false,
        'show_ui'            => true,
        'show_in_rest'       => true,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-calendar',
    );

    register_post_type( 'edmm_meeting_minutes', $args );
}
add_action( 'init', __NAMESPACE__ . '\register_meeting_minutes_cpt' );

// Add Meta Fields using ACF Free
function add_acf_meta_fields() {
    if ( function_exists( 'acf_add_local_field_group' ) ) {
        acf_add_local_field_group( array(
            'key'    => 'group_edmm_meeting_minutes',
            'title'  => 'Meeting Details',
            'fields' => array(
                array(
                    'key'   => 'field_edmm_meeting_date',
                    'label' => 'Meeting Date',
                    'name'  => 'edmm_meeting_date',
                    'required' => 1,
                    'type'  => 'date_picker',
                ),
                array(
                    'key'   => 'field_edmm_meeting_agenda_url',
                    'label' => 'Meeting Agenda URL',
                    'name'  => 'edmm_meeting_agenda_url',
                    'type'  => 'url',
                ),
                array(
                    'key'   => 'field_edmm_meeting_notes_url',
                    'label' => 'Meeting Notes URL',
                    'name'  => 'edmm_meeting_notes_url',
                    'type'  => 'url',
                ),
                array(
                    'key'   => 'field_edmm_meeting_not_held',
                    'label' => 'Meeting Not Held',
                    'name'  => 'edmm_meeting_not_held',
                    'type'  => 'true_false',
                    'ui'    => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'edmm_meeting_minutes',
                    ),
                ),
            ),
        ) );
    }
}
add_action( 'acf/init', __NAMESPACE__ . '\add_acf_meta_fields' );

// Create REST Route for Meeting Minutes
function register_meeting_minutes_rest_route() {
    register_rest_route( 'edmm/v1', '/meeting-minutes/', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\get_meeting_minutes',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_meeting_minutes_rest_route' );

function get_meeting_minutes( $request ) {
    
    $params = $request->get_query_params();
    error_log('Received parameters: ' . print_r($params, true));

    $page = isset($params['page']) ? absint($params['page']) : 1;
    error_log('Page received (from $params): ' . $page);

    $held_date_format = isset($params['held-date-format']) ? sanitize_text_field($params['held-date-format']) : 'Y/m/d';
    $not_held_date_format = isset($params['not-held-date-format']) ? sanitize_text_field($params['not-held-date-format']) : 'Y/m';

    $args = array(
        'post_type'      => 'edmm_meeting_minutes',
        'posts_per_page' => 20,
        'paged'          => $page,
        'meta_key'       => 'edmm_meeting_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    );

    if ( ! empty( $params['included-years'] ) ) {
        $years = explode( ',', $params['included-years'] );
        $args['date_query'] = array(
            array(
                'year' => $years,
            ),
        );
    }

    $query = new \WP_Query( $args );
    $meetings = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $meeting_date       = get_field( 'edmm_meeting_date' );
            $meeting_not_held   = get_field( 'edmm_meeting_not_held' );
            $meeting_agenda_url = get_field( 'edmm_meeting_agenda_url' );
            $meeting_notes_url  = get_field( 'edmm_meeting_notes_url' );

            error_log('Raw meeting date value from ACF: ' . print_r($meeting_date, true));

            $date_object = \DateTime::createFromFormat('d/m/Y', $meeting_date);
            if ($date_object) {
                $formatted_date = $meeting_not_held 
                    ? $date_object->format($not_held_date_format) 
                    : $date_object->format($held_date_format);
            } else {
                $formatted_date = '<span class="sr-text screen-reader-text">Date not available</span>'; // Handle the case when date parsing fails
            }

            $meetings[] = array(
                'title'  => get_the_title(),
                'date'   => $formatted_date,
                'agenda' => $meeting_not_held 
                    ? 'Meeting not held' 
                    : ( $meeting_agenda_url ? $meeting_agenda_url : '<span class="sr-text screen-reader-text">Agenda not available</span>' ),
                'notes'  => $meeting_notes_url ? $meeting_notes_url : '<span class="sr-text screen-reader-text">Meeting notes not available</span>',
            );
        }
        wp_reset_postdata();
    }

    return rest_ensure_response( array(
        'meetings'      => $meetings,
        'max_num_pages' => $query->max_num_pages,
        'current_page'  => $page,
    ) );
}

// Shortcode to Display Meeting Minutes Table
function meeting_minutes_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'included-years'    => '',
        'hide-title'        => 'false',
        'hide-date'         => 'false',
        'hide-agenda'       => 'false',
        'hide-notes'        => 'false',
        'held-date-format'  => 'Y/m/d',
        'not-held-date-format' => 'Y/m'
    ), $atts, 'edmm_meeting_minutes' );

    $api_url = rest_url( 'edmm/v1/meeting-minutes/' ) . '?included-years=' . urlencode( $atts['included-years'] ) 
        . '&held-date-format=' . urlencode( $atts['held-date-format'] )
        . '&not-held-date-format=' . urlencode( $atts['not-held-date-format'] );

    ob_start();
    ?>
    <div id="edmm-meeting-minutes-table"></div>
    <div id="edmm-pagination"></div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        let currentPage = 1;
        const apiUrl = '<?php echo esc_url_raw( $api_url ); ?>';

        const renderTable = (data) => {
            let table = '<table><thead><tr>';
            if ('<?php echo esc_js( $atts['hide-title'] ); ?>' !== 'true') {
                table += '<th>Title</th>';
            }
            if ('<?php echo esc_js( $atts['hide-date'] ); ?>' !== 'true') {
                table += '<th>Date</th>';
            }
            if ('<?php echo esc_js( $atts['hide-agenda'] ); ?>' !== 'true') {
                table += '<th>Agenda</th>';
            }
            if ('<?php echo esc_js( $atts['hide-notes'] ); ?>' !== 'true') {
                table += '<th>Notes</th>';
            }
            table += '</tr></thead><tbody>';
            data.meetings.forEach(meeting => {
                table += '<tr>';
                if ('<?php echo esc_js( $atts['hide-title'] ); ?>' !== 'true') {
                    table += '<td scope="row">' + meeting.title + '</td>';
                }
                if ('<?php echo esc_js( $atts['hide-date'] ); ?>' !== 'true') {
                    table += '<td>' + meeting.date + '</td>';
                }
                if ('<?php echo esc_js( $atts['hide-agenda'] ); ?>' !== 'true') {
                    table += '<td>' + (meeting.agenda.startsWith('http') ? '<a href="' + meeting.agenda + '" aria-label="Agenda for ' + meeting.date + '">View Agenda</a>' : meeting.agenda) + '</td>';
                }
                if ('<?php echo esc_js( $atts['hide-notes'] ); ?>' !== 'true') {
                    table += '<td>' + (meeting.notes.startsWith('http') ? '<a href="' + meeting.notes + '" aria-label="Notes for ' + meeting.date + '">View Notes</a>' : meeting.notes) + '</td>';
                }
                table += '</tr>';
            });
            table += '</tbody></table>';
            document.getElementById('edmm-meeting-minutes-table').innerHTML = table;

            let pagination = '';
            if (data.max_num_pages > 1) {
                pagination += '<nav aria-label="Pagination"><ul class="usa-pagination">';
                for (let i = 1; i <= data.max_num_pages; i++) {
                    pagination += '<li class="usa-pagination__item' + (i === data.current_page ? ' usa-current' : '') + '"><a href="#" data-page="' + i + '" class="usa-pagination__link" aria-label="Page ' + i + '">' + i + '</a></li>';
                }
                pagination += '</ul></nav>';
            }
            document.getElementById('edmm-pagination').innerHTML = pagination;

            document.querySelectorAll('#edmm-pagination a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    currentPage = parseInt(this.getAttribute('data-page'));
                    console.log('Pagination clicked, currentPage updated to:', currentPage); // Debugging current page update
                    fetchMeetings();
                });
            });
        };

        const fetchMeetings = () => {
            const urlWithPage = apiUrl + '&page=' + encodeURIComponent(currentPage);
            console.log('Fetching from URL:', urlWithPage); // Debug to verify final URL

            fetch(urlWithPage)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data); // Debug response data
                    renderTable(data);
                })
                .catch(error => {
                    console.error('There was a problem with the fetch operation:', error);
                });
        };

        fetchMeetings(); // Initial fetch to load the first page
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'edmm_meeting_minutes', __NAMESPACE__ . '\meeting_minutes_shortcode' );