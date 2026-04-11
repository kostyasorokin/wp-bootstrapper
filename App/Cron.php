<?php
/**
 * Cron intervals registration
 *
 * Handles the addition of custom intervals to the WordPress cron schedules.
 *
 * @package    WP_Bootstrapper
 * @subpackage Cron
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WPB\Attributes\Hook;

defined( 'ABSPATH' ) || exit;

class Cron {

    /**
     * Registers custom intervals for WordPress cron jobs.
     *
     * @param array $schedules Array of existing cron schedules.
     *
     * @return array Modified array containing the new cron schedules.
     */
    #[Hook( 'cron_schedules' )]
    public function addIntervals( array $schedules ): array {
        $new_schedules = [
            'month'        => [
                'interval' => MONTH_IN_SECONDS,
                'display'  => esc_html__( 'Every month', 'wp-bootstrapper' ),
            ],
            'three_months' => [
                'interval' => MONTH_IN_SECONDS * 3,
                'display'  => esc_html__( 'Every three months', 'wp-bootstrapper' ),
            ],
            'semiannually' => [
                'interval' => MONTH_IN_SECONDS * 6,
                'display'  => esc_html__( 'Semiannually', 'wp-bootstrapper' ),
            ],
            'yearly'       => [
                'interval' => YEAR_IN_SECONDS,
                'display'  => esc_html__( 'Yearly', 'wp-bootstrapper' ),
            ],
        ];

        return array_merge( $schedules, $new_schedules );
    }

}