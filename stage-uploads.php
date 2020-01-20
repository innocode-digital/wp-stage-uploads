<?php
/**
 * Plugin Name: Stage Uploads
 * Description: Replaces local uploads URL.
 * Version:  0.2.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Tested up to: 5.3.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// stage only
if (
    defined( 'ENVIRONMENT' ) && ENVIRONMENT == 'production'
    || defined( 'WP_ENV' ) && WP_ENV == 'production'
) {
    return;
}

function stage_uploads_url_filter( $uri, $cache_key = '' ) {
    $prod_upload_dir_baseurl = rtrim( esc_url( get_option( 'stage-uploads-url' ) ), '/' );

    if ( empty( $prod_upload_dir_baseurl ) ) {
        return $uri;
    }

    if ( $cache_key && false !== ( $cached_uri = get_transient( "stage_uploads:$cache_key" ) ) ) {
        return $cached_uri;
    }

    $upload_dir = wp_upload_dir();
    $upload_dir_baseurl = $upload_dir['baseurl'];
    $upload_dir_basedir = $upload_dir['basedir'];

    if ( 0 !== mb_strpos( $uri, $upload_dir_baseurl ) ) {
        return $uri;
    }

    $relative = mb_substr( $uri, mb_strlen( rtrim( $upload_dir_baseurl, '/' ) ) );
    $local_path = rtrim( $upload_dir_basedir, DIRECTORY_SEPARATOR ) . $relative;

    if ( ! file_exists( $local_path ) ) {
        $uri = $prod_upload_dir_baseurl . $relative;
    }

    if ( $cache_key ) {
        try {
            $random_number = random_int( 1, 7 );
        } catch ( Exception $exception ) {
            $random_number = 7;
        }

        set_transient( "stage_uploads:$cache_key", $uri, $random_number * DAY_IN_SECONDS );
    }

    return $uri;
}

add_action( 'admin_init', function () {
    register_setting( 'general', 'stage-uploads-url', 'esc_url' );
    add_settings_field( 'stage-uploads-url', __( 'Prod Uploads Path' ), function () {
        $value = esc_url( get_option( 'stage-uploads-url' ) );
        echo "<input 
            type=\"url\" 
            id=\"stage-uploads-url\" 
            name=\"stage-uploads-url\" 
            value=\"$value\" 
            placeholder=\"https://prod.site.com/wp-content/uploads/sites/2/\" 
            class=\"regular-text ltr\" 
        />";
    }, 'general', 'default', [
        'label_for' => 'stage-uploads-url',
    ] );
} );

add_filter( 'wp_get_attachment_url', function ( $url, $attachment_id ) {
    return stage_uploads_url_filter( $url, $attachment_id );
}, 99, 2 );

add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
    foreach ( $sources as $key => $source ) {
        $sources[ $key ]['url'] = stage_uploads_url_filter( $source['url'], "$attachment_id:{$source['descriptor']}:{$source['value']}" );
    }

    return $sources;
}, 99, 5 );

add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id, $size ) {
    if ( false !== $image ) {
        $image[0] = stage_uploads_url_filter( $image[0], "$attachment_id:$size" );
    }

    return $image;
}, 99, 3 );

add_filter( 'the_content', function ( $content ) {
    return preg_replace_callback( '/https?:\\/\\/.+(png|jpeg|jpg|gif|bmp|svg|pdf|webp)/Ui', function ( $match ) {
        return stage_uploads_url_filter( $match[0], sanitize_key( $match[0] ) );
    }, $content );
}, 99 );
