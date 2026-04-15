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
    #[Hook( 'init', priority: 1 )]
    public function set_referer_cookie(): void {
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
            return;
        }

        // Keep the full referrer URL (including query params) if it's valid.
        $rawReferer = isset( $_SERVER['HTTP_REFERER'] )
            ? esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) )
            : '';
        $referer    = '' !== $rawReferer && wp_http_validate_url( $rawReferer ) ? $rawReferer : 'n/a';

        // Set the 'origin' cookie for 1 day with secure defaults.
        setcookie(
            'origin',
            $referer,
            [
                'expires' => time() + DAY_IN_SECONDS,
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

}
