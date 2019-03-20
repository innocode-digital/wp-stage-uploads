<?php
/**
 * Plugin Name: Stage Uploads
 * Description: Replaces local uploads URL.
 * Version:  0.1.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Tested up to: 5.1.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// stage only
if ( defined( 'ENVIRONMENT' ) && ENVIRONMENT == 'production' ) {
    return;
}

function stage_uploads_url_filter( $uri ) {
    $upload_dir = wp_upload_dir();
    $upload_dir_baseurl = $upload_dir['baseurl'];
    $upload_dir_basedir = $upload_dir['basedir'];
    $prod_upload_dir_baseurl = rtrim( esc_url( get_option( 'stage-uploads-url' ) ), '/' );

    if ( empty( $prod_upload_dir_baseurl ) ) {
        return $uri;
    }

    if ( 0 === mb_strpos( $uri, $upload_dir_baseurl ) ) {
        $relative = mb_substr( $uri, mb_strlen( rtrim( $upload_dir_baseurl, '/' ) ) );
        $local_path = rtrim( $upload_dir_basedir, DIRECTORY_SEPARATOR ) . $relative;

        if ( ! file_exists( $local_path ) ) {
            $prod_uri = $prod_upload_dir_baseurl . $relative;
            return $prod_uri;
        }

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

add_filter( 'wp_get_attachment_url', 'stage_uploads_url_filter', 99 );

add_filter( 'wp_calculate_image_srcset', function ( $sources ) {
    if ( is_array( $sources ) ) {
        foreach ( $sources as $key => $source ) {
            $sources[ $key ]['url'] = stage_uploads_url_filter( $source['url'] );
        }
    }

    return $sources;
}, 99 );

add_filter( 'wp_get_attachment_image_src', function ( $image ) {
    if ( false !== $image ) {
        $image[0] = stage_uploads_url_filter( $image[0] );
    }

    return $image;
} );

add_filter( 'the_content', function ( $content ) {
    return preg_replace_callback( '/https?:\\/\\/.+(png|jpeg|jpg|gif|bmp|svg|pdf)/Ui', function ( $match ) {
        return stage_uploads_url_filter( $match[0] );
    }, $content );
}, 99 );
