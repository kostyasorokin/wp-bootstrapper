<?php
/**
 * Class: Head
 *
 * @package    WP_Bootstrapper
 * @subpackage Head
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Head {

    /**
     * Cleans up unnecessary and potentially insecure meta tags from the <head>.
     * * Hooked to 'init' so it executes after WordPress core has added its default actions,
     * but before the page actually starts rendering.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function clean_head(): void {
        // Removes the WordPress version
        Options::is( 'disable_wp_generator', true ) && remove_action( 'wp_head', 'wp_generator' );

        // Removes RSD (Really Simple Discovery)
        Options::is( 'disable_rsd_link', true ) && remove_action( 'wp_head', 'rsd_link' );

        // Removes Windows Live Writer manifest
        Options::is( 'disable_wlwmanifest_link', true ) && remove_action( 'wp_head', 'wlwmanifest_link' );

        // Removes the short link from head and HTTP headers
        if ( Options::is( 'disable_wp_shortlink', true ) ) {
            remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
            remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        }
    }

}