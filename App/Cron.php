<?php
/**
 * Cron intervals registration
 *
 * Handles the addition of custom intervals to the WordPress cron schedules.
 *
 * @package    KS_Bootstrapper
 * @subpackage Cron
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;

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
    public function add_intervals( array $schedules ): array {
        $new_schedules = [
            'month'        => [
                'interval' => MONTH_IN_SECONDS,
                'display'  => esc_html__( 'Every month', 'ks-bootstrapper' ),
            ],
            'three_months' => [
                'interval' => MONTH_IN_SECONDS * 3,
                'display'  => esc_html__( 'Every three months', 'ks-bootstrapper' ),
            ],
            'semiannually' => [
                'interval' => MONTH_IN_SECONDS * 6,
                'display'  => esc_html__( 'Semiannually', 'ks-bootstrapper' ),
            ],
            'yearly'       => [
                'interval' => YEAR_IN_SECONDS,
                'display'  => esc_html__( 'Yearly', 'ks-bootstrapper' ),
            ],
        ];

        return array_merge( $schedules, $new_schedules );
    }

}