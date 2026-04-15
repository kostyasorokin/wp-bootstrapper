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
     * Removes emoji assets and related integrations.
     */
    #[Hook( 'init' )]
    public function disable_emojis(): void {
        if ( ! Options::is( 'disable_emojis', true ) ) {
            return;
        }

        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    }

    /**
     * Removes the emoji plugin from TinyMCE.
     *
     * @param array $plugins TinyMCE plugin list.
     *
     * @return array
     */
    #[Hook( 'tiny_mce_plugins' )]
    public function disable_emojis_tinymce( array $plugins ): array {
        if ( ! Options::is( 'disable_emojis', true ) ) {
            return $plugins;
        }

        return array_values( array_diff( $plugins, [ 'wpemoji' ] ) );
    }

    /**
     * Removes the emoji CDN host from DNS prefetch hints.
     *
     * @param array  $urls          Resource hint URLs.
     * @param string $relation_type Resource hint relation type.
     *
     * @return array
     */
    #[Hook( 'wp_resource_hints', accepted_args: 2 )]
    public function disable_emojis_remove_dns_prefetch( array $urls, string $relation_type ): array {
        if ( ! Options::is( 'disable_emojis', true ) || 'dns-prefetch' !== $relation_type ) {
            return $urls;
        }

        foreach ( $urls as $key => $url ) {
            if ( is_string( $url ) && false !== strpos( $url, 's.w.org' ) ) {
                unset( $urls[ $key ] );
            }
        }

        return $urls;
    }

    /**
     * Removes Recent Comments widget inline styles from the document head.
     */
    #[Hook( 'widgets_init' )]
    public function remove_recent_comments_style(): void {
        if ( ! Options::is( 'disable_recent_comments_style', true ) ) {
            return;
        }

        global $wp_widget_factory;

        if (
            isset( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] ) &&
            is_object( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] )
        ) {
            remove_action(
                'wp_head',
                [ $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ]
            );
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
     * Sends Content-Security-Policy (CSP) HTTP header.
     * Restricts resource loading to the site's own origin only.
     */
    #[Hook( 'send_headers' )]
    public function content_security_policy_header(): void {
        Options::is( 'content_security_policy' ) && header( "Content-Security-Policy: default-src 'self'" );
    }

    /**
     * Secures content by preventing resource loading from external sources.
     * WARNING: 'default-src self' may block external fonts, scripts, or maps.
     */
    #[Hook( 'wp_head', priority: 1 )]
    public function content_security_policy(): void {
        if ( Options::is( 'content_security_policy' ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'">' . PHP_EOL;
        }
    }

    /**
     * Set X-Frame-Options header before any output.
     */
    #[Hook( 'send_headers' )]
    public function x_frame_header(): void {
        Options::is( 'x_frame_options' ) && header( 'X-Frame-Options: DENY' );
    }

    /**
     * Disables wptexturize conversions when requested in settings.
     *
     * @param bool $run Whether wptexturize should run.
     *
     * @return bool
     */
    #[Hook( 'run_wptexturize' )]
    public function run_wptexturize( bool $run ): bool {
        return Options::is( 'disable_wptexturize' ) ? false : $run;
    }

    /**
     * Add X-Frame-Options meta tag as a fallback.
     *
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
