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

/**
 * Registers the 'Meeting Minutes' custom post type.
 *
 * This function defines the labels and arguments for the 'Meeting Minutes' custom post type
 * and registers it with WordPress using the `register_post_type` function.
 *
 * @return void
 */
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

/**
 * Adds ACF meta fields for the 'Meeting Minutes' post type.
 *
 * This function checks if the Advanced Custom Fields (ACF) plugin is active,
 * and if so, it defines custom fields for storing meeting-related metadata
 * such as the meeting date, agenda URL, and notes URL.
 *
 * @return void
 */
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

/**
 * Registers a custom REST API route for retrieving meeting minutes.
 *
 * This function creates a custom REST API endpoint at `/edmm/v1/meeting-minutes/`
 * that allows users to retrieve meeting minutes using GET requests.
 *
 * @return void
 */
function register_meeting_minutes_rest_route() {
    register_rest_route( 'edmm/v1', '/meeting-minutes/', array(
        'methods'             => 'GET',
        'callback'            => __NAMESPACE__ . '\get_meeting_minutes',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_meeting_minutes_rest_route' );

/**
 * Retrieves meeting minutes via a custom REST API endpoint.
 *
 * This function handles the logic for retrieving meeting minutes from the database
 * based on the specified parameters, such as page number and posts per page.
 * It also formats the response to include the necessary information like title,
 * date, agenda, and notes.
 *
 * @param \WP_REST_Request $request The request object containing the parameters.
 * @return \WP_REST_Response The response object containing the meeting minutes.
 */
function get_meeting_minutes( $request ) {
    
    $params         = $request->get_query_params();
    $page           = isset($params['page']) ? absint($params['page']) : 1;
    $posts_per_page = isset($params['posts_per_page']) ? absint($params['posts_per_page']) : 20;

    $held_date_format = isset($params['held_date_format']) ? sanitize_text_field($params['held_date_format']) : 'Y/m/d';
    $not_held_date_format = isset($params['not_held_date_format']) ? sanitize_text_field($params['not_held_date_format']) : 'Y/m';

    $args = array(
        'post_type'      => 'edmm_meeting_minutes',
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $page,
        'meta_key'       => 'edmm_meeting_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'edmm_meeting_date',
                'compare' => 'EXISTS',
            ),
        ),
    );

    if ( ! empty( $params['included_years'] ) ) {
        $years = explode( ',', $params['included_years'] );
    
        // Add an OR clause for each year to match posts in those years
        $year_queries = array( 'relation' => 'OR' );
    
        foreach ( $years as $year ) {
            $year = intval( $year ); // Ensure the year is an integer
            $year_queries[] = array(
                'key'     => 'edmm_meeting_date',
                'value'   => array( $year . '-01-01', $year . '-12-31' ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            );
        }
    
        $args['meta_query'][] = $year_queries;
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

            //error_log('Raw meeting date value from ACF: ' . print_r($meeting_date, true));

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
        'total_entries' => $query->found_posts,
    ) );
}

/**
 * Shortcode to display meeting minutes in a table format.
 *
 * This function provides a shortcode `[edmm_meeting_minutes]` that fetches and displays
 * meeting minutes in a paginated table format. Users can customize the shortcode's behavior
 * by passing attributes such as included years, date formats, and visibility options.
 *
 * @param array $atts Shortcode attributes.
 * @return string The HTML output of the table.
 */
function meeting_minutes_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'included_years'       => '',
        'hide_title'           => 'false',
        'hide_date'            => 'false',
        'hide_agenda'          => 'false',
        'hide_notes'           => 'false',
        'held_date_format'     => 'Y/m/d',
        'not_held_date_format' => 'Y/m',
        'class'                => '',
        'posts_per_page'       => 20,
    ), $atts, 'edmm_meeting_minutes' );

    $api_url = rest_url( 'edmm/v1/meeting-minutes/' ) . '?included_years=' . urlencode( $atts['included_years'] ) 
        . '&held_date_format=' . urlencode( $atts['held_date_format'] )
        . '&not_held_date_format=' . urlencode( $atts['not_held_date_format'] )
        . '&posts_per_page=' . absint( $atts['posts_per_page'] ); 

    ob_start();
    ?>
    <div id="edmm-meeting-minutes-table"></div>
    <div id="edmm-pagination"></div>
    <div id="pagination-info" aria-live="polite" aria-atomic="true" style="position: absolute; left: -9999px;"></div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentPage = 1;
            const apiUrl = '<?php echo esc_url_raw( $api_url ); ?>';
            const maxSlots = 7;
            const postsPerPage = <?php echo absint( $atts['posts_per_page'] ); ?>;

            const renderTable = (data) => {
                let tableClass = '<?php echo esc_js( $atts['class'] ); ?>';
                let table = '<table class="' + tableClass + '"><thead><tr>';
                
                if ('<?php echo esc_js( $atts['hide_title'] ); ?>' !== 'true') {
                    table += '<th>Title</th>';
                }
                if ('<?php echo esc_js( $atts['hide_date'] ); ?>' !== 'true') {
                    table += '<th>Date</th>';
                }
                if ('<?php echo esc_js( $atts['hide_agenda'] ); ?>' !== 'true') {
                    table += '<th>Agenda</th>';
                }
                if ('<?php echo esc_js( $atts['hide_notes'] ); ?>' !== 'true') {
                    table += '<th>Notes</th>';
                }
                table += '</tr></thead><tbody>';
                data.meetings.forEach(meeting => {
                    table += '<tr>';
                    if ('<?php echo esc_js( $atts['hide_title'] ); ?>' !== 'true') {
                        table += '<td scope="row">' + meeting.title + '</td>';
                    }
                    if ('<?php echo esc_js( $atts['hide_date'] ); ?>' !== 'true') {
                        table += '<td>' + meeting.date + '</td>';
                    }
                    if ('<?php echo esc_js( $atts['hide_agenda'] ); ?>' !== 'true') {
                        table += '<td>' + (meeting.agenda.startsWith('http') ? '<a href="' + meeting.agenda + '" aria-label="Agenda for ' + meeting.date + '">View Agenda</a>' : meeting.agenda) + '</td>';
                    }
                    if ('<?php echo esc_js( $atts['hide_notes'] ); ?>' !== 'true') {
                        table += '<td>' + (meeting.notes.startsWith('http') ? '<a href="' + meeting.notes + '" aria-label="Notes for ' + meeting.date + '">View Notes</a>' : meeting.notes) + '</td>';
                    }
                    table += '</tr>';
                });
                table += '</tbody></table>';
                document.getElementById('edmm-meeting-minutes-table').innerHTML = table;

                // Update aria-live region with pagination info
                const totalEntries = data.total_entries;
                const startEntry = (currentPage - 1) * postsPerPage + 1;
                const endEntry = Math.min(currentPage * postsPerPage, totalEntries);

                const paginationInfo = `Showing ${startEntry} to ${endEntry} of ${totalEntries} entries`;
                document.getElementById('pagination-info').textContent = paginationInfo;

                let pagination = '';
                if (data.max_num_pages > 1) {
                    pagination += '<nav aria-label="Pagination"><div class="edmm-pagination-buttons">';

                    // Add Previous button if not on the first page
                    if (data.current_page > 1) {
                        pagination += '<button type="button" class="edmm-pagination-button prev" aria-label="Previous Page" data-page="' + (data.current_page - 1) + '">Previous</button>';
                    }

                    // Calculate which pages to show
                    let slots = calculatePaginationSlots(data.current_page, data.max_num_pages);

                    // Render pagination buttons
                    slots.forEach(slot => {
                        if (slot === '...') {
                            pagination += '<span class="pagination-ellipsis">...</span>';
                        } else {
                            pagination += '<button type="button" class="edmm-pagination-button' + (slot === data.current_page ? ' current' : '') + '" data-page="' + slot + '" aria-label="Page ' + slot + '">' + slot + '</button>';
                        }
                    });

                    // Add Next button if not on the last page
                    if (data.current_page < data.max_num_pages) {
                        pagination += '<button type="button" class="edmm-pagination-button next" aria-label="Next Page" data-page="' + (data.current_page + 1) + '">Next</button>';
                    }

                    pagination += '</div></nav>';
                }
                document.getElementById('edmm-pagination').innerHTML = pagination;

                // Add event listeners to buttons
                document.querySelectorAll('#edmm-pagination button').forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const targetPage = parseInt(this.getAttribute('data-page'));
                        if (!isNaN(targetPage) && targetPage >= 1 && targetPage <= data.max_num_pages) {
                            currentPage = targetPage;
                            fetchMeetings();
                        }
                    });
                });
            };

            const calculatePaginationSlots = (currentPage, totalPages) => {
                let slots = [];

                // Always show the first page
                slots.push(1);

                // If there are less than or equal to maxSlots pages, show all of them
                if (totalPages <= maxSlots) {
                    for (let i = 2; i <= totalPages; i++) {
                        slots.push(i);
                    }
                } else {
                    // Show first page, current page, previous, next, and last page, plus ellipsis
                    if (currentPage > 3) {
                        slots.push('...');
                    }

                    // Add the previous, current, and next pages
                    let start = Math.max(2, currentPage - 1);
                    let end = Math.min(currentPage + 1, totalPages - 1);

                    for (let i = start; i <= end; i++) {
                        slots.push(i);
                    }

                    // If there are more pages after the current set, add an ellipsis
                    if (currentPage < totalPages - 2) {
                        slots.push('...');
                    }

                    // Always show the last page
                    slots.push(totalPages);
                }

                return slots;
            };

            const fetchMeetings = () => {
                const urlWithPage = apiUrl + '&page=' + encodeURIComponent(currentPage);
                fetch(urlWithPage)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        renderTable(data);
                    })
                    .catch(error => {
                        console.error('There was a problem with the fetch operation:', error);
                    });
            };

            fetchMeetings(); // Initial fetch to load the first page
        });
    </script>
    <style>
        .edmm-pagination-buttons {
            display: flex;
            gap: 5px;
        }
        .edmm-pagination-button {
            padding: 5px 10px;
            border: none;
            background-color: #007bff;
            color: white;
            cursor: pointer;
        }
        .edmm-pagination-button.current {
            background-color: #0056b3;
        }
        .edmm-pagination-button[disabled] {
            background-color: #ccc;
            cursor: not-allowed;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode( 'edmm_meeting_minutes', __NAMESPACE__ . '\meeting_minutes_shortcode' );
