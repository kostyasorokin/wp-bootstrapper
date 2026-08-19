<?php
/**
 * Users Customizations
 *
 * Handles modifications to user profiles and contact methods.
 *
 * @package    KS_Bootstrapper
 * @subpackage Users
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Users {

    /**
     * Adds the social contact methods to the user profile.
     *
     * Methods contributed by the theme or by other plugins are preserved; only keys
     * of the same name are overwritten.
     *
     * @param array $methods Existing contact methods.
     *
     * @return array Modified contact methods.
     */
    #[Hook( 'user_contactmethods' )]
    public function updateContactMethods( array $methods ): array {
        return array_merge( $methods, [
            'x'                => 'X',
            'linkedin'         => 'LinkedIn',
            'youtube'          => 'YouTube',
            'facebook'         => 'Facebook',
            'instagram'        => 'Instagram',
            'telegram'         => 'Telegram',
            'telegram_channel' => 'Telegram ' . __( 'channel', 'ks-bootstrapper' ),
            'telegram_group'   => 'Telegram ' . __( 'group', 'ks-bootstrapper' ),
        ] );
    }

}