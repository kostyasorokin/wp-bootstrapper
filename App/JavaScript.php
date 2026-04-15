<?php
/**
 * JavaScript Optimization and Management
 *
 * @package    WP_Bootstrapper
 * @subpackage Scripts
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WP_Scripts;
use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class JavaScript {

    /**
     * jQuery & jQuery Core
     * This may break plugins that rely on jQuery (e.g., Contact Form 7, WooCommerce).
     */
    #[Hook( 'wp_enqueue_scripts', priority: 100 )]
    public function jquery(): void {
        // Never disable it in the admin panel, otherwise it will stop working
        if ( is_admin() ) {
            return;
        }

        if ( ! Options::is( 'jquery', true ) ) {
            wp_deregister_script( 'jquery' );
            wp_deregister_script( 'jquery-core' );
        }
    }

    /**
     * jQuery Migrate
     *
     * @param WP_Scripts $scripts The WP_Scripts instance (passed by reference).
     *
     * @return void
     */
    #[Hook( 'wp_default_scripts' )]
    public function jquery_migrate( WP_Scripts $scripts ): void {
        // Only target front-end and check if the feature is enabled.
        if ( is_admin() || Options::is( 'jquery_migrate', true ) ) {
            return;
        }

        if ( isset( $scripts->registered['jquery'] ) ) {
            $script = $scripts->registered['jquery'];

            if ( ! empty( $script->deps ) ) {
                $key = array_search( 'jquery-migrate', $script->deps, true );

                if ( false !== $key ) {
                    unset( $script->deps[ $key ] );
                }
            }
        }
    }

    /**
     * Completely disables oEmbed functionality (scripts, links, and API).
     * * Even though this removes discovery links (HTML), we keep it here
     * to have a single "oEmbed killer" method alongside JS optimizations.
     */
    #[Hook( 'init' )]
    public function oembed_full(): void {
        // If the checkbox is NOT checked, we do nothing and return.
        if ( ! Options::is( 'oembed_full', true ) ) {
            return;
        }

        // 1. Stop the host JS (wp-embed.min.js) from loading in the footer/head
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );

        // 2. Remove oEmbed discovery links (REST API & XML-RPC)
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );

        // 3. Disable the oEmbed-specific REST API routes
        remove_action( 'rest_api_init', 'wp_oembed_register_route' );

        // 4. Disable oEmbed discovery for external content
        add_filter( 'embed_oembed_discover', '__return_false' );

        // 5. Remove the filter that attempts to parse oEmbed results
        remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
    }

    /**
     * The Heartbeat API.
     * Heartbeat API provides real-time data syncing between the server
     * and the dashboard (e.g., autosave, post locking).
     */
    #[Hook( 'init', priority: 1 )]
    public function stopHeartbeat(): void {
        // If the option is disabled in settings, the heartbeat script is deregistered.
        if ( ! Options::is( 'heartbeat_api', true ) ) {
            wp_deregister_script( 'heartbeat' );
        }
    }

    /**
     * The WordPress autosave script.
     */
    #[Hook( 'wp_print_scripts' )]
    #[Hook( 'admin_print_scripts' )]
    public function autosave_script(): void {
        ! Options::is( 'autosave_script' ) && wp_deregister_script( 'autosave' );
    }

    /**
     * Adds async and defer attributes to specific script tags.
     *
     * @param string $tag    The original script tag.
     *
     * @param string $handle The script's registered handle.
     * @param string $src    The script's source URL.
     *
     * @return string Modified or original script tag.
     */
    #[Hook( 'script_loader_tag', priority: 10, accepted_args: 3 )]
    public function set_async_defer_attribute( string $tag, string $handle, string $src ): string {
        // Do not work in the admin panel to avoid breaking the editor scripts
        if ( is_admin() ) {
            return $tag;
        }
        // List of script handles to be loaded asynchronously
        $async_scripts = [ 'main', 'index', 'wp-embed' ];

        if ( in_array( $handle, $async_scripts, true ) ) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Modifying existing script tag.
            return sprintf(
                '<script async defer src="%s" id="%s-js"></script>' . "\n",
                esc_url( $src ),
                esc_attr( $handle )
            );
        }

        return $tag;
    }
}
