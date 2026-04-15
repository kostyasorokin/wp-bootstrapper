<?php
/**
 * Plugin Name: WP Bootstrapper
 * Description: Foundational set of tools to initialize and optimize WordPress.
 * Version: 1.0.0
 * Author: Konstantin Sorokin
 * Author URI: https://konstantinsorokin.com
 * Text Domain: wp-bootstrapper
 * Domain Path: /languages/
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package    WP_Bootstrapper
 * @author     Konstantin Sorokin
 * @license    GPL-3.0-or-later
 * @link       https://konstantinsorokin.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Constants
 */
define( 'WPB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPB_OPTION_PREFIX', '_wpb_' );

/**
 * Load security hardening early so activation/deactivation hooks can use it.
 */
require_once __DIR__ . '/App/Security.php';

/**
 * Immediate Constants Bootstrap
 * We use direct get_option calls to define system constants before the engine starts.
 */
$options = get_option( 'wpb_options', [] );

! empty( $options['disable_wp_cron'] ) && ! defined( 'DISABLE_WP_CRON' ) && define( 'DISABLE_WP_CRON', true );
! empty( $options['disallow_file_mods'] ) && ! defined( 'DISALLOW_FILE_MODS' ) && define( 'DISALLOW_FILE_MODS', true );
! empty( $options['disable_post_revisions'] ) && ! defined( 'WP_POST_REVISIONS' ) && define( 'WP_POST_REVISIONS', false );
! empty( $options['autosave_interval'] ) && ! defined( 'AUTOSAVE_INTERVAL' ) && define( 'AUTOSAVE_INTERVAL', max( 1, (int) $options['autosave_interval'] ) );

/**
 * Initialize the Bootstrapper Architecture
 *
 * @throws \ReflectionException
 */
add_action( 'plugins_loaded', function (): void {
    $composerAutoload = __DIR__ . '/vendor/autoload.php';

    // Graceful fallback if Composer hasn't been run
    if ( file_exists( $composerAutoload ) ) {
        require_once $composerAutoload;

        // Enable cache regeneration only if WP_DEBUG is enabled
        $isDebug = defined( 'WP_DEBUG' ) && WP_DEBUG;

        $manager = new \WPB\Core\Manager(
            appDir: __DIR__ . '/App',
            namespacePrefix: 'WPB\\',
            cacheFile: __DIR__ . '/cache/hooks_cache.php', // Ensure the 'cache' folder exists and is writable
            isDebug: $isDebug
        );

        $manager->boot();
    } else {
        // Display an admin notice if autoload.php is missing
        add_action( 'admin_notices', function (): void {
            printf(
                '<div class="error"><p>%s</p></div>',
                esc_html__( 'WP Bootstrapper: Please run "composer install" in the plugin directory to activate it.', 'wp-bootstrapper' )
            );
        } );
    }
}, 1 ); // Priority 1 ensures the bootstrapper runs before most other plugins

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, static function (): void {
    add_option( 'wpb_flush_rewrite_rules_flag', true );
    \WPB\Security::activate();
} );

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, static function (): void {
    flush_rewrite_rules( false );
    \WPB\Security::deactivate();
} );

/**
 * Smart flush mechanism
 * Runs on 'init' but ONLY executes once after plugin activation
 */
add_action( 'init', static function (): void {
    if ( get_option( 'wpb_flush_rewrite_rules_flag' ) ) {
        flush_rewrite_rules( false );
        delete_option( 'wpb_flush_rewrite_rules_flag' );
    }
}, 99 );
