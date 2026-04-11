<?php
/**
 * Global Options Registry Helper
 *
 * Provides a centralized, static interface for accessing plugin settings
 * with internal caching to minimize database hits.
 *
 * @package    WP_Bootstrapper
 * @subpackage Settings
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Settings\Helpers;

defined( 'ABSPATH' ) || exit;

final class Options {

    /**
     * Cached options array.
     *
     * @var array|null
     */
    private static ?array $data = null;

    /**
     * Retrieves a specific option value from the unified plugin settings array.
     *
     * @param string $key     The setting key defined in Config.php.
     * @param mixed  $default Optional. Default value if the key is not found. Default false.
     *
     * @return mixed The option value or the default value.
     */
    public static function get( string $key, mixed $default = false ): mixed {
        if ( null === self::$data ) {
            // Access the option name defined in your Settings logic
            self::$data = get_option( 'wpb_options', [] );
        }

        return self::$data[ $key ] ?? $default;
    }

    /**
     * Shorthand for checking if a checkbox option is enabled (truthy).
     *
     * @param string $key     The setting key.
     * @param bool   $default Optional. Default value if not set in DB. Default false.
     *
     * @return bool
     */
    public static function is( string $key, bool $default = false ): bool {
        return (bool) self::get( $key, $default );
    }

}