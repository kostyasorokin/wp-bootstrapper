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

        // RSS Feed Links support.
        // If the option is disabled in settings, we remove theme support for RSS feeds.
        if ( ! Options::is( 'automatic_feed_links' ) ) {
            remove_theme_support( 'automatic-feed-links' );

            // Additionally, we remove links to category and comment feeds
            remove_action( 'wp_head', 'feed_links_extra', 3 );
            remove_action( 'wp_head', 'feed_links', 2 );
        }
    }

    /**
     * Disables automatic phone number detection on iOS devices.
     * Adds a meta tag to prevent Safari from automatically turning
     * phone-like numbers into clickable links.
     */
    #[Hook( 'wp_head', priority: 5 )]
    public function phone_detection(): void {
        if ( Options::is( 'phone_detection' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<meta name="format-detection" content="telephone=no">' . PHP_EOL;
        }
    }

    /**
     * Defines the geographical location of the content.
     * Helpful for local SEO to specify country and region (e.g., UA-KH).
     */
    #[Hook( 'wp_head', 5 )]
    public function geo_region(): void {
        $region = Options::get( 'geo_region' );

        if ( ! empty( $region ) ) {
            printf(
                '<meta name="geo.region" content="%s">%s',
                esc_attr( $region ),
                PHP_EOL
            );
        }
    }

    /**
     * Prevents embedding the page in an iframe for security.
     * Protects the site against clickjacking attacks.
     */
    #[Hook( 'wp_head', priority: 5 )]
    public function x_frame_options(): void {
        if ( Options::is( 'x_frame_options' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<meta http-equiv="X-Frame-Options" content="DENY">' . PHP_EOL;
        }
    }

}