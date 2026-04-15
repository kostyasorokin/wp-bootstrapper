<?php
/**
 * Class:  Backend
 *
 * @package    WP_Bootstrapper
 * @subpackage Backend
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
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
    public function remove_wp_logo( WP_Admin_Bar $wp_admin_bar ): void {
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

    /**
     * Registers the Action Scheduler menu item under Tools if not already present.
     * Useful for managing background tasks when WooCommerce or other plugins use AS.
     *
     * @link /wp-admin/tools.php?page=action-scheduler
     */
    #[Hook( 'admin_menu', priority: 9999 )]
    public function register_action_scheduler_menu(): void {
        global $submenu;

        $already_registered = false;

        // Scan the "Tools" submenu for an existing action-scheduler slug
        if ( isset( $submenu['tools.php'] ) && is_array( $submenu['tools.php'] ) ) {
            foreach ( $submenu['tools.php'] as $item ) {
                if ( isset( $item[2] ) && 'action-scheduler' === $item[2] ) {
                    $already_registered = true;
                    break;
                }
            }
        }

        // Register only if missing and user has permissions
        if ( ! $already_registered && current_user_can( 'manage_options' ) ) {
            add_management_page(
                __( 'Action Scheduler', 'wp-bootstrapper' ),
                __( 'Action Scheduler', 'wp-bootstrapper' ),
                'manage_options',
                'action-scheduler'
            );
        }
    }

}