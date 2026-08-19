<?php
/**
 * TranslatePress Integration and Cleanup
 *
 * @package    KS_Bootstrapper
 * @subpackage Plugins
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper\Plugins;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class TranslatePress {

    /**
     * Check if TranslatePress is active.
     */
    private function is_active(): bool {
        return class_exists( 'TRP_Translate_Press' );
    }

    /**
     * Dequeue default TranslatePress language switcher styles.
     *
     * Covers every stylesheet TranslatePress 3.x can register: the shortcode and
     * floater sheets of the legacy switcher, and the sheet of the V2 switcher
     * that ships as the default since 3.3.
     */
    #[Hook( 'wp_enqueue_scripts', priority: 9000 )]
    public function dequeue_style(): void {
        if ( ! $this->is_active() || ! Options::is( 'trp_disable_default_css' ) ) {
            return;
        }

        wp_dequeue_style( 'trp-language-switcher-style' );
        wp_dequeue_style( 'trp-floater-language-switcher-style' );
        wp_dequeue_style( 'trp-language-switcher-v2' );
    }

    /**
     * Change language name in the language switcher
     *
     * @param string $name              The original language name.
     * @param string $code              The language code.
     * @param string $english_or_native Format type.
     *
     * @return string Modified language name.
     */
    #[Hook( 'trp_beautify_language_name', accepted_args: 3 )]
    public function shorten_language_names( string $name, string $code, string $english_or_native ): string {
        if ( ! $this->is_active() ) {
            return $name;
        }

        $code = strtolower( $code );

        $languages = [
            'ru_ru' => 'RU',
            'uk'    => 'UA',
            'en_us' => 'EN',
            'en_gb' => 'EN',
            'en'    => 'EN',
            'de_de' => 'DE',
            'fr_fr' => 'FR',
            'es_es' => 'ES',
            'it_it' => 'IT',
            'nl_nl' => 'NL',
            'pl_pl' => 'PL',
            'cs_cz' => 'CS',
            'sk_sk' => 'SK',
            'hu_hu' => 'HU',
            'ro_ro' => 'RO',
            'bg_bg' => 'BG',
            'sr_rs' => 'SR',
            'hr_hr' => 'HR',
            'sl_si' => 'SL',
            'pt_pt' => 'PT',
            'fi_fi' => 'FI',
            'sv_se' => 'SV',
            'da_dk' => 'DA',
            'no_no' => 'NO',
            'is_is' => 'IS',
            'et_ee' => 'ET',
            'lv_lv' => 'LV',
            'lt_lt' => 'LT',
            'el_gr' => 'EL',
            'mt_mt' => 'MT',
            'ga_ie' => 'GA',
        ];

        return $languages[ $code ] ?? $name;
    }

}