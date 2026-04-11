<?php
/**
 * Users Customizations
 *
 * Handles modifications to user profiles and contact methods.
 *
 * @package    WP_Bootstrapper
 * @subpackage Users
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Users {

    /**
     * Updates the available contact methods in the user profile.
     *
     * @param array $methods Existing contact methods.
     *
     * @return array Modified contact methods.
     */
    #[Hook( 'user_contactmethods' )]
    public function updateContactMethods( array $methods ): array {
        return [
            'x'                => 'X',
            'linkedin'         => 'LinkedIn',
            'youtube'          => 'YouTube',
            'facebook'         => 'Facebook',
            'instagram'        => 'Instagram',
            'telegram'         => 'Telegram',
            'telegram_channel' => 'Telegram ' . __( 'channel', 'wp-bootstrapper' ),
            'telegram_group'   => 'Telegram ' . __( 'group', 'wp-bootstrapper' ),
        ];
    }

}