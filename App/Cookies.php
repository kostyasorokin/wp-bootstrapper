<?php
/**
 * Cookies
 *
 * @package    WP_Bootstrapper
 * @subpackage Cookies
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Cookies {

    /**
     * Set Referer Cookie For New Users
     *
     * Sets a cookie named 'origin' for non-admin visitors if it hasn't been set yet.
     *
     * @return void
     */
    /**
     * Sets a cookie with the referrer URL for new users.
     */
    #[Hook( 'init', priority: 1 )]
    public function setRefererCookie(): void {
        // Check if the feature is enabled in settings
        if ( ! Options::is( 'set_referer_cookie_for_new_users', true ) ) {
            return;
        }

        // Return early if in admin area or cookie already set
        if ( is_admin() || isset( $_COOKIE['origin'] ) ) {
            return;
        }

        // Check if headers have already been sent
        if ( headers_sent() ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'WP Bootstrapper: Headers already sent, "origin" cookie skipped.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log.error_log_found
            }
            return;
        }

        // Retrieve and sanitize HTTP_REFERER
        $referer = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_URL ) ?: 'n/a';

        // Set the 'origin' cookie for 1 day
        setcookie( 'origin', $referer, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
    }

}