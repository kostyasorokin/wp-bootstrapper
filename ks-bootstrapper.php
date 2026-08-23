<?php
/**
 * Plugin Name: KS Bootstrapper
 * Description: Foundational set of tools to initialize and optimize WordPress.
 * Version: 2.1.0
 * Author: Konstantin Sorokin
 * Author URI: https://konstantinsorokin.com
 * Text Domain: ks-bootstrapper
 * Domain Path: /languages/
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package    KS_Bootstrapper
 * @author     Konstantin Sorokin
 * @license    GPL-3.0-or-later
 * @link       https://konstantinsorokin.com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Constants
 */
define( 'KS_BOOTSTRAPPER_VERSION', '2.1.0' );
define( 'KS_BOOTSTRAPPER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KS_BOOTSTRAPPER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KS_BOOTSTRAPPER_OPTION_PREFIX', '_ks_bootstrapper_' );

/**
 * Composer Autoloader
 *
 * Loaded while the plugin file is being included, not on 'plugins_loaded'. Activation runs
 * in a request where that action has already fired: WordPress includes the plugin file and
 * calls the activation hook immediately afterwards, so an autoloader deferred to
 * 'plugins_loaded' would never exist by the time Security::activate() needs Options.
 */
$ks_bootstrapper_autoload = __DIR__ . '/vendor/autoload.php';

// Graceful fallback if Composer hasn't been run.
if ( ! is_readable( $ks_bootstrapper_autoload ) ) {
    add_action( 'admin_notices', static function (): void {
        printf(
            '<div class="error"><p>%s</p></div>',
            esc_html__( 'KS Bootstrapper: Please run "composer install" in the plugin directory to activate it.', 'ks-bootstrapper' )
        );
    } );

    return;
}

require_once $ks_bootstrapper_autoload;
unset( $ks_bootstrapper_autoload );

/**
 * Immediate Constants Bootstrap
 * We use direct get_option calls to define system constants before the engine starts.
 */
$options = get_option( 'ks_bootstrapper_options', [] );

! empty( $options['disable_wp_cron'] ) && ! defined( 'DISABLE_WP_CRON' ) && define( 'DISABLE_WP_CRON', true );
! empty( $options['disallow_file_mods'] ) && ! defined( 'DISALLOW_FILE_MODS' ) && define( 'DISALLOW_FILE_MODS', true );
! empty( $options['disable_post_revisions'] ) && ! defined( 'WP_POST_REVISIONS' ) && define( 'WP_POST_REVISIONS', false );
! empty( $options['autosave_interval'] ) && ! defined( 'AUTOSAVE_INTERVAL' ) && define( 'AUTOSAVE_INTERVAL', max( 1, (int) $options['autosave_interval'] ) );

/**
 * Initialize the Bootstrapper Architecture
 *
 * @throws \ReflectionException
 */
add_action( 'plugins_loaded', static function (): void {
    $manager = new \KonstantinSorokin\Bootstrapper\Core\Manager(
        appDir: __DIR__ . '/App',
        namespacePrefix: 'KonstantinSorokin\\Bootstrapper\\',
        cacheFile: __DIR__ . '/cache/hooks_cache.php', // Recreated automatically when the sources change
        version: KS_BOOTSTRAPPER_VERSION
    );

    $manager->boot();
}, 1 ); // Priority 1 ensures the bootstrapper runs before most other plugins

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, static function (): void {
    add_option( 'ks_bootstrapper_flush_rewrite_rules_flag', true );

    // Drops the seeding stamp so the next request writes every declared default
    // that has never been saved into ks_bootstrapper_options.
    delete_option( 'ks_bootstrapper_options_version' );

    \KonstantinSorokin\Bootstrapper\Security::activate();
} );

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, static function (): void {
    flush_rewrite_rules( false );
    \KonstantinSorokin\Bootstrapper\Security::deactivate();
} );

/**
 * Smart flush mechanism
 * Runs on 'init' but ONLY executes once after plugin activation
 */
add_action( 'init', static function (): void {
    if ( get_option( 'ks_bootstrapper_flush_rewrite_rules_flag' ) ) {
        flush_rewrite_rules( false );
        delete_option( 'ks_bootstrapper_flush_rewrite_rules_flag' );
    }
}, 99 );
