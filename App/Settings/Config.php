<?php
/**
 * Plugin Settings Configuration
 *
 * Registers and configures the main settings page for the WP Bootstrapper plugin,
 * using a fluent builder interface to define tabs, sections, and fields.
 *
 * @package    WP_Bootstrapper
 * @subpackage Settings
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Settings;

use WPB\Attributes\Hook;
use WPB\Settings\Enums\FieldType;
use WPB\Settings\Definitions\Section;
use WPB\Settings\Definitions\Tab;

defined( 'ABSPATH' ) || exit;

class Config {

    /**
     * Registers the settings page configuration.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function register(): void {
        // Include plugin functions if not already available to use is_plugin_active()
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        Settings::make(
            optionName: 'wpb_options',
            pageId: 'bootstrapper',
            title: __( 'Bootstrapper', 'wp-bootstrapper' )
        )
            ->addTab( 'system', __( 'System', 'wp-bootstrapper' ), function ( Tab $tab ) {
                $tab->addSection( 'system_cron', __( 'Cron', 'wp-bootstrapper' ), '', function ( Section $section ) {
                    $section->addField( 'disable_wp_cron', FieldType::CHECKBOX, [
                        'label'          => 'DISABLE_WP_CRON',
                        'label_checkbox' => __( 'Disable WordPress Virtual Cron', 'wp-bootstrapper' ),
                        'description'    => __( 'Disables wp-cron.php execution on every page load. Recommended if you have a real system cron job configured.', 'wp-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
                $tab->addSection( 'system_javascript', __( 'JavaScript', 'wp-bootstrapper' ), '', function ( Section $section ) {
                    $section->addField( 'jquery', FieldType::CHECKBOX, [
                        'label'          => 'jquery.min.js',
                        'label_checkbox' => 'jQuery',
                        'description'    => __( 'Warning: Removes jQuery from the frontend. Only use this if your theme and plugins do not rely on jQuery. This will significantly improve PageSpeed, but might break things.', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->addField( 'jquery_migrate', FieldType::CHECKBOX, [
                        'label'          => 'jquery-migrate.js',
                        'label_checkbox' => 'jQuery Migrate',
                        'description'    => __( 'The migration layer for jQuery. Warning: This may break very old themes or plugins that rely on deprecated jQuery functions.', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->addField( 'oembed_full', FieldType::CHECKBOX, [
                        'label'          => 'oEmbed API',
                        'label_checkbox' => __( 'oEmbed features', 'wp-bootstrapper' ),
                        'description'    => __( 'wp-embed.min.js, discovery links (REST API/XML-RPC), and API routes.', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->addField( 'heartbeat_api', FieldType::CHECKBOX, [
                        'label'          => 'Heartbeat API',
                        'label_checkbox' => 'Heartbeat API',
                        'description'    => __( 'The Heartbeat API script (wp-heartbeat.min.js). This saves server resources but disables features like auto-saving and post-lock notifications.', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] );
                } );
                $tab->addSection( 'system_media', __( 'Media', 'wp-bootstrapper' ), '', function ( Section $section ) {
                    $section->addField( 'big_image_size_threshold', FieldType::CHECKBOX, [
                        'label'          => 'big_image_size_threshold',
                        'label_checkbox' => __( 'Disable automatic scaling of large images', 'wp-bootstrapper' ),
                        'description'    => __( 'When enabled, WordPress will not downscale images larger than 2560px.', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] );
                } );
                $tab->addSection( 'system_files', __( 'Files', 'wp-bootstrapper' ), '', function ( Section $section ) {
                    $section->addField( 'disallow_file_mods', FieldType::CHECKBOX, [
                        'label'          => 'DISALLOW_FILE_MODS',
                        'label_checkbox' => __( 'Disable File Modifications', 'wp-bootstrapper' ),
                        'description'    => __( 'Completely disables the built-in WordPress theme and plugin editor, as well as the ability to update or install new plugins and themes from the admin dashboard. Highly recommended for production sites.', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] );
                } );
            } )
            ->addTab( 'admin', __( 'Admin', 'wp-bootstrapper' ), function ( Tab $tab ) {
                $tab->addSection( 'admin_cleanup', '', '', function ( Section $section ) {
                    $section->addField( 'disable_admin_bar_menu_logo', FieldType::CHECKBOX, [
                        'label'          => 'admin_bar_menu',
                        'label_checkbox' => __( 'Removes the WordPress logo from the admin bar', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] )
                            ->addField( 'disable_welcome_panel', FieldType::CHECKBOX, [
                                'label'          => 'welcome_panel',
                                'label_checkbox' => __( 'Remove the Welcome Panel', 'wp-bootstrapper' ),
                                'description'    => __( 'Hides the "Welcome to WordPress" box from the dashboard.', 'wp-bootstrapper' ),
                                'default'        => true,
                            ] )
                            ->addField( 'disable_admin_footer_text', FieldType::CHECKBOX, [
                                'label'          => 'admin_footer_text',
                                'label_checkbox' => __( 'Remove "Thank you for creating with WordPress" text', 'wp-bootstrapper' ),
                                'default'        => true,
                            ] )
                            ->addField( 'disable_admin_footer_version', FieldType::CHECKBOX, [
                                'label'          => 'update_footer',
                                'label_checkbox' => __( 'Remove WordPress version from the footer', 'wp-bootstrapper' ),
                                'default'        => true,
                            ] );
                } );
            } )
            ->addTab( 'head', __( 'Head', 'wp-bootstrapper' ), function ( Tab $tab ) {
                $tab->addSection( 'head_cleanup', '', '', function ( Section $section ) {
                    $section->addField( 'disable_wp_generator', FieldType::CHECKBOX, [
                        'label'          => 'wp_generator',
                        'label_checkbox' => __( 'Removes the WordPress version tag', 'wp-bootstrapper' ),
                        'default'        => true,
                    ] )
                            ->addField( 'disable_rsd_link', FieldType::CHECKBOX, [
                                'label'          => 'rsd_link',
                                'label_checkbox' => __( 'Removes RSD (Really Simple Discovery) link', 'wp-bootstrapper' ),
                                'default'        => true,
                            ] )
                            ->addField( 'disable_wlwmanifest_link', FieldType::CHECKBOX, [
                                'label'          => 'wlwmanifest_link',
                                'label_checkbox' => __( 'Removes Windows Live Writer manifest link', 'wp-bootstrapper' ),
                                'default'        => true,
                            ] )
                            ->addField( 'disable_wp_shortlink', FieldType::CHECKBOX, [
                                'label'          => 'wp_shortlink',
                                'label_checkbox' => __( 'Removes the short link for the current page', 'wp-bootstrapper' ),
                                'description'    => __( 'Removes rel="shortlink" from the &lt;head&gt; and the Link header from server responses.', 'wp-bootstrapper' ),
                                'default'        => true,
                            ] );
                } );
            } )
            ->addTab( 'cookies', __( 'Cookies', 'wp-bootstrapper' ), function ( Tab $tab ) {
                $tab->addSection( 'cookies_section', '', '', function ( Section $section ) {
                    $section->addField( 'set_referer_cookie_for_new_users', FieldType::CHECKBOX, [
                        'label'          => 'cookie "origin"',
                        'label_checkbox' => __( 'Sets a cookie named "origin" for non-admin visitors if it hasn’t been set yet', 'wp-bootstrapper' ),
                    ] );
                } );

                //                    $tab->addSection( 'meta_tags', __( 'Meta tags', 'wp-bootstrapper' ), '&lt;meta ... &gt;', function ( Section $section ) {
                //                        $section->addField( 'metaXFrameOptions', FieldType::CHECKBOX, [
                //                            'label'          => 'X-Frame-Options DENY',
                //                            'label_checkbox' => __( 'Prevents embedding the page in an iframe', 'wp-bootstrapper' ),
                //                            'description'    => '&lt;meta http-equiv="X-Frame-Options" content="DENY"&gt;',
                //                        ] )
                //                                ->addField( 'metaGeoRegion', FieldType::TEXT, [
                //                                    'label'       => 'geo.region',
                //                                    'description' => __( 'Defines the geographical location...', 'wp-bootstrapper' ),
                //                                ] );
                //                    } );
            } )
        //                ->addTab( 'telegram', __( 'Telegram Notifications', 'wp-bootstrapper' ), function ( Tab $tab ) {
        //                    $tab->addSection( 'bot_settings', __( 'Bot Settings', 'wp-bootstrapper' ), '', function ( Section $section ) {
        //                        $section->addField( 'TelegramBotToken', FieldType::TEXT, [
        //                            'label'       => __( 'Bot Token', 'wp-bootstrapper' ),
        //                            'description' => '<a href="https://core.telegram.org/bots#6-botfather" target="_blank">How get token</a>',
        //                        ] )
        //                                ->addField( 'TelegramChatIDs', FieldType::TEXTAREA, [
        //                                    'label'       => __( 'Chat IDs', 'wp-bootstrapper' ),
        //                                    'description' => __( 'The separator is a comma without a space...', 'wp-bootstrapper' ),
        //                                ] );
        //                    } );
        //                } )
        //                ->when( is_plugin_active( 'woocommerce/woocommerce.php' ), function ( Settings $settings ) {
        //                    $settings->addTab( 'woocommerce', 'WooCommerce', function ( Tab $tab ) {
        //                        $tab->addSection( 'wc_notif', __( 'Notification: WooCommerce', 'wp-bootstrapper' ), '', function ( Section $section ) {
        //                            $section->addField( 'TelegramWooCommerceOrderNotification', FieldType::CHECKBOX, [
        //                                'label' => __( 'Orders', 'wp-bootstrapper' ),
        //                            ] )
        //                                    ->addField( 'TelegramWooCommerceLowStockNotification', FieldType::CHECKBOX, [
        //                                        'label' => __( 'Low Stock', 'wp-bootstrapper' ),
        //                                    ] );
        //                        } );
        //                    } );
        //                } )
            ->boot(); // Register the hooks in WordPress
    }

}