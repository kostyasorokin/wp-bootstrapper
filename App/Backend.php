<?php
/**
 * Class:  Backend
 *
 * @author Konstantin Sorokin
 * @link   https://konstantinsorokin.com
 */

namespace WPB;

use WP_Admin_Bar;
use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Backend {

    /**
     * Removes the WordPress logo from the admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
     *
     * @return void
     */
    #[Hook( 'admin_bar_menu', priority: 999 )]
    public function removeWpLogo( WP_Admin_Bar $wp_admin_bar ): void {
        Options::is( 'disable_admin_bar_menu_logo', true ) && $wp_admin_bar->remove_node( 'wp-logo' );
    }

    /**
     * Removes the WordPress version from the bottom right of the admin footer.
     *
     * @param string $content The existing footer content.
     *
     * @return string Empty string to hide the version.
     */
    #[Hook( 'update_footer', priority: 99 )]
    public function remove_footer_version( string $content ): string {
        return Options::is( 'disable_admin_footer_version' ) ? '' : $content;
    }

    /**
     * Changes or removes the "Thank you for creating with WordPress" text
     * from the bottom left of the admin footer.
     *
     * @param string $text The existing footer text.
     *
     * @return string New text or empty string.
     */
    #[Hook( 'admin_footer_text', priority: 99 )]
    public function remove_footer_text( string $text ): string {
        return Options::is( 'disable_admin_footer_text' ) ? '' : $text;
    }

    /**
     * Removes the welcome panel from the WordPress dashboard.
     *
     * @return void
     */
    #[Hook( 'admin_init' )]
    public function remove_welcome_panel(): void {
        if ( Options::is( 'disable_welcome_panel' ) ) {
            remove_action( 'welcome_panel', 'wp_welcome_panel' );
        }
    }

}