<?php
/**
 * JavaScript Optimization and Management
 *
 * @package    WP_Bootstrapper
 * @subpackage Scripts
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WP_Scripts;
use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class JavaScript {

    /**
     * Removes jQuery Migrate from the jQuery dependencies.
     *
     * @param WP_Scripts $scripts The WP_Scripts instance (passed by reference).
     *
     * @return void
     */
    #[Hook( 'wp_default_scripts' )]
    public function disable_jquery_migrate( WP_Scripts $scripts ): void {
        // Only target front-end and check if the feature is enabled.
        if ( is_admin() || Options::is( 'jquery_migrate' ) ) {
            return;
        }

        if ( isset( $scripts->registered['jquery'] ) ) {
            $script = $scripts->registered['jquery'];

            if ( ! empty( $script->deps ) ) {
                $key = array_search( 'jquery-migrate', $script->deps, true );

                if ( false !== $key ) {
                    unset( $script->deps[ $key ] );
                }
            }
        }
    }

}