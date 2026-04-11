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
     * Removes jQuery Migrate from the jQuery dependencies.
     *
     * @param WP_Scripts $scripts The WP_Scripts instance (passed by reference).
     *
     * @return void
     */
    #[Hook( 'wp_default_scripts' )]
    public function disable_jquery_migrate( WP_Scripts $scripts ): void {
        // Only target front-end and check if the feature is enabled.
        if ( is_admin() || Options::is( 'jquery_migrate' ) ) {
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

}