<?php
/**
 * Class:  Backend
 *
 * @package    KS_Bootstrapper
 * @subpackage Backend
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use WP_Admin_Bar;
use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

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
        return Options::is( 'disable_admin_footer_version', true ) ? '' : $content;
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
        return Options::is( 'disable_admin_footer_text', true ) ? '' : $text;
    }

    /**
     * Removes the welcome panel from the WordPress dashboard.
     *
     * @return void
     */
    #[Hook( 'admin_init' )]
    public function remove_welcome_panel(): void {
        if ( Options::is( 'disable_welcome_panel', true ) ) {
            remove_action( 'welcome_panel', 'wp_welcome_panel' );
        }
    }

    /**
     * Registers the Action Scheduler screen under Tools if not already present.
     *
     * Action Scheduler moves its own admin screen under WooCommerce > Status when
     * WooCommerce is active, which leaves the queue without a Tools entry. The item
     * is registered only when the library is actually loaded, so that the slug always
     * has something to render.
     *
     * @link /wp-admin/tools.php?page=action-scheduler
     *
     * @return void
     */
    #[Hook( 'admin_menu', priority: 9999 )]
    public function register_action_scheduler_menu(): void {
        if ( ! Options::is( 'action_scheduler_menu', true ) ) {
            return;
        }

        // Without the library there is no screen to render, and the slug would fatal on click
        if ( ! class_exists( 'ActionScheduler_AdminView' ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( $this->tools_page_exists( 'action-scheduler' ) ) {
            return;
        }

        add_management_page(
            __( 'Action Scheduler', 'ks-bootstrapper' ),
            __( 'Action Scheduler', 'ks-bootstrapper' ),
            'manage_options',
            'action-scheduler',
            static fn() => \ActionScheduler_AdminView::instance()->render_admin_ui()
        );
    }

    /**
     * Checks whether a page slug is already registered in the "Tools" submenu.
     *
     * @param string $slug The menu slug to look for.
     *
     * @return bool True when the slug is already present under Tools.
     */
    private function tools_page_exists( string $slug ): bool {
        global $submenu;

        if ( empty( $submenu['tools.php'] ) || ! is_array( $submenu['tools.php'] ) ) {
            return false;
        }

        foreach ( $submenu['tools.php'] as $item ) {
            if ( isset( $item[2] ) && $slug === $item[2] ) {
                return true;
            }
        }

        return false;
    }

}