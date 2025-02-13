<?php
/**
 * Plugin Name: Hello Homer
 * Plugin URI: https://example.com/hello-homer
 * Description: Display random quotes from The Simpsons using the Frinkiac API
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hello-homer
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Fetch a random quote from Frinkiac API or cache
 *
 * @return array|bool Quote data on success, false on failure
 */
function hello_homer_get_quote() {
    // Check transient first
    $cached_quote = get_transient( 'hello_homer_quote' );
    if ( false !== $cached_quote ) {
        return $cached_quote;
    }

    $response = wp_remote_get( 'https://frinkiac.com/api/random' );
    
    if ( is_wp_error( $response ) ) {
        error_log( 'Hello Homer API Error: ' . $response->get_error_message() );
        return array(
            'quote'    => __( "D'oh! We couldn't connect to the API!", 'hello-homer' ),
            'episode'  => '',
            'season'   => '',
            'image'    => '',
        );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body );
    
    if ( empty( $data ) || empty( $data->Episode->Title ) || empty( $data->Subtitles[0]->Content ) ) {
        return array(
            'quote'    => __( "D'oh! We couldn't connect to the API!", 'hello-homer' ),
            'episode'  => '',
            'season'   => '',
            'image'    => '',
        );
    }

    $quote_data = array(
        'quote'    => esc_html( $data->Subtitles[0]->Content ),
        'episode'  => esc_html( $data->Episode->Title ),
        'season'   => esc_html( $data->Episode->Season ),
        'image'    => sprintf( 'https://frinkiac.com/img/%s/%d.jpg', 
            esc_attr( $data->Episode->Key ), 
            intval( $data->Frame->Timestamp )
        ),
    );

    // Cache for the period set in settings (default 1 hour)
    $cache_time = get_option( 'hello_homer_cache_time', HOUR_IN_SECONDS );
    set_transient( 'hello_homer_quote', $quote_data, $cache_time );

    return $quote_data;
}

/**
 * Add quote to admin footer
 */
function hello_homer_admin_footer() {
    $quote_data = hello_homer_get_quote();
    
    if ( ! $quote_data ) {
        return;
    }

    $display_image = get_option( 'hello_homer_show_image', 'yes' );
    $display_episode = get_option( 'hello_homer_show_episode', 'yes' );
    
    $output = '<div id="homer">';
    
    if ( 'yes' === $display_image && ! empty( $quote_data['image'] ) ) {
        $output .= sprintf( 
            '<img src="%s" alt="%s" /><br>', 
            esc_url( $quote_data['image'] ),
            esc_attr( $quote_data['quote'] )
        );
    }
    
    $output .= sprintf( '<p class="homer-quote">%s</p>', $quote_data['quote'] );
    
    if ( 'yes' === $display_episode && ! empty( $quote_data['episode'] ) ) {
        $output .= sprintf(
            '<p class="homer-episode">Season %d - %s</p>',
            intval( $quote_data['season'] ),
            $quote_data['episode']
        );
    }
    
    $output .= '</div>';
    echo $output;
}

/**
 * Add styles for the quote
 */
function hello_homer_admin_css() {
    ?>
    <style type="text/css">
    #homer {
        float: right;
        padding: 5px 10px;
        margin: 0;
        font-size: 11px;
        color: #999;
        text-align: right;
    }
    #homer img {
        max-width: 200px;
        height: auto;
        margin-bottom: 5px;
    }
    .homer-quote {
        margin: 0 0 5px 0;
    }
    .homer-episode {
        margin: 0;
        font-size: 10px;
        font-style: italic;
    }
    </style>
    <?php
}

/**
 * Add settings page
 */
function hello_homer_add_settings_page() {
    add_options_page(
        __( 'Hello Homer Settings', 'hello-homer' ),
        __( 'Hello Homer', 'hello-homer' ),
        'manage_options',
        'hello-homer',
        'hello_homer_settings_page'
    );
}
add_action( 'admin_menu', 'hello_homer_add_settings_page' );

/**
 * Register settings
 */
function hello_homer_register_settings() {
    register_setting( 'hello-homer', 'hello_homer_show_image' );
    register_setting( 'hello-homer', 'hello_homer_show_episode' );
    register_setting( 'hello-homer', 'hello_homer_cache_time' );
}
add_action( 'admin_init', 'hello_homer_register_settings' );

/**
 * Settings page callback
 */
function hello_homer_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'hello-homer' );
            $show_image = get_option( 'hello_homer_show_image', 'yes' );
            $show_episode = get_option( 'hello_homer_show_episode', 'yes' );
            $cache_time = get_option( 'hello_homer_cache_time', HOUR_IN_SECONDS );
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Show Screenshot', 'hello-homer' ); ?></th>
                    <td>
                        <select name="hello_homer_show_image">
                            <option value="yes" <?php selected( $show_image, 'yes' ); ?>><?php _e( 'Yes', 'hello-homer' ); ?></option>
                            <option value="no" <?php selected( $show_image, 'no' ); ?>><?php _e( 'No', 'hello-homer' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Show Episode Info', 'hello-homer' ); ?></th>
                    <td>
                        <select name="hello_homer_show_episode">
                            <option value="yes" <?php selected( $show_episode, 'yes' ); ?>><?php _e( 'Yes', 'hello-homer' ); ?></option>
                            <option value="no" <?php selected( $show_episode, 'no' ); ?>><?php _e( 'No', 'hello-homer' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Cache Duration', 'hello-homer' ); ?></th>
                    <td>
                        <select name="hello_homer_cache_time">
                            <option value="<?php echo HOUR_IN_SECONDS; ?>" <?php selected( $cache_time, HOUR_IN_SECONDS ); ?>><?php _e( '1 Hour', 'hello-homer' ); ?></option>
                            <option value="<?php echo DAY_IN_SECONDS; ?>" <?php selected( $cache_time, DAY_IN_SECONDS ); ?>><?php _e( '1 Day', 'hello-homer' ); ?></option>
                            <option value="<?php echo WEEK_IN_SECONDS; ?>" <?php selected( $cache_time, WEEK_IN_SECONDS ); ?>><?php _e( '1 Week', 'hello-homer' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p>
            <button class="button" onclick="jQuery.post(ajaxurl, {action: 'hello_homer_refresh'}, function() { location.reload(); });">
                <?php _e( 'Get New Quote Now', 'hello-homer' ); ?>
            </button>
        </p>
    </div>
    <?php
}

/**
 * Ajax handler to refresh quote
 */
function hello_homer_refresh_quote() {
    delete_transient( 'hello_homer_quote' );
    wp_die();
}
add_action( 'wp_ajax_hello_homer_refresh', 'hello_homer_refresh_quote' );

add_action( 'admin_footer_text', 'hello_homer_admin_footer' );
add_action( 'admin_head', 'hello_homer_admin_css' ); 
