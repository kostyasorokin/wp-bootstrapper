<?php
/**
 * Global Options Registry Helper
 *
 * Provides a centralized, static interface for accessing plugin settings
 * with internal caching to minimize database hits.
 *
 * @package    KS_Bootstrapper
 * @subpackage Settings
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper\Settings\Helpers;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;

defined( 'ABSPATH' ) || exit;

final class Options {

    /**
     * Name of the option row holding every setting.
     */
    private const OPTION_NAME = 'ks_bootstrapper_options';

    /**
     * Name of the option row mirroring the defaults declared in Config.php.
     */
    private const DEFAULTS_OPTION = 'ks_bootstrapper_options_defaults';

    /**
     * Name of the option row holding the version the settings were last seeded for.
     */
    private const VERSION_OPTION = 'ks_bootstrapper_options_version';

    /**
     * Cached options array.
     *
     * @var array|null
     */
    private static ?array $data = null;

    /**
     * Cached defaults array.
     *
     * @var array|null
     */
    private static ?array $defaults = null;

    /**
     * Retrieves a specific option value from the unified plugin settings array.
     *
     * A key that has never been saved falls back to the default declared for it in Config.php,
     * so the settings page and the modules can never disagree about what a fresh install does.
     * The $default argument only covers keys that no settings field declares.
     *
     * @param string $key     The setting key defined in Config.php.
     * @param mixed  $default Optional. Default value if the key is declared nowhere. Default false.
     *
     * @return mixed The option value, the declared default, or the given default value.
     */
    public static function get( string $key, mixed $default = false ): mixed {
        if ( null === self::$data ) {
            // Access the option name defined in your Settings logic
            $stored = get_option( self::OPTION_NAME, [] );

            self::$data = is_array( $stored ) ? $stored : [];
        }

        if ( array_key_exists( $key, self::$data ) ) {
            return self::$data[ $key ];
        }

        $defaults = self::defaults();

        return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;
    }

    /**
     * Shorthand for checking if a checkbox option is enabled (truthy).
     *
     * @param string $key     The setting key.
     * @param bool   $default Optional. Default value if the key is declared nowhere. Default false.
     *
     * @return bool
     */
    public static function is( string $key, bool $default = false ): bool {
        return (bool) self::get( $key, $default );
    }

    /**
     * Returns the defaults declared by the settings fields.
     *
     * The map is mirrored into its own option row, so it is available to every module from the
     * first hook of the request onwards — long before Config::register() runs on 'init'.
     *
     * @return array
     */
    public static function defaults(): array {
        if ( null === self::$defaults ) {
            $stored = get_option( self::DEFAULTS_OPTION, [] );

            self::$defaults = is_array( $stored ) ? $stored : [];
        }

        return self::$defaults;
    }

    /**
     * Publishes the defaults declared by a settings page to the runtime.
     *
     * Called by Settings::boot(). Declarations are merged over the stored map rather than
     * replacing it, so fields living behind a conditional tab keep their defaults while the
     * plugin they belong to is inactive.
     *
     * @param string $optionName The option row the settings page writes to.
     * @param array  $defaults   Map of field id to declared default value.
     *
     * @return void
     */
    public static function register_defaults( string $optionName, array $defaults ): void {
        if ( self::OPTION_NAME !== $optionName || empty( $defaults ) ) {
            return;
        }

        $stored = self::defaults();
        $merged = array_merge( $stored, $defaults );

        if ( $merged !== $stored ) {
            update_option( self::DEFAULTS_OPTION, $merged );
        }

        self::$defaults = $merged;

        self::seed( $defaults );
    }

    /**
     * Writes the declared defaults into the settings row once per plugin version.
     *
     * Values already stored always win — seeding only fills in keys that have never been saved,
     * so an upgrade can add a field without touching anything the owner has configured.
     *
     * @param array $defaults Map of field id to declared default value.
     *
     * @return void
     */
    private static function seed( array $defaults ): void {
        $version = defined( 'KS_BOOTSTRAPPER_VERSION' ) ? KS_BOOTSTRAPPER_VERSION : '';

        if ( get_option( self::VERSION_OPTION ) === $version ) {
            return;
        }

        $stored = get_option( self::OPTION_NAME, [] );
        $stored = is_array( $stored ) ? $stored : [];
        $seeded = $stored + $defaults;

        if ( $seeded !== $stored ) {
            update_option( self::OPTION_NAME, $seeded );
        }

        update_option( self::VERSION_OPTION, $version );

        self::$data = $seeded;
    }

    /**
     * Drops the request-scoped cache whenever the settings row is written.
     *
     * @return void
     */
    #[Hook( 'add_option_ks_bootstrapper_options' )]
    #[Hook( 'update_option_ks_bootstrapper_options' )]
    #[Hook( 'delete_option_ks_bootstrapper_options' )]
    public function flush(): void {
        self::$data     = null;
        self::$defaults = null;
    }

}
