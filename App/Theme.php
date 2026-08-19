<?php
/**
 * Theme UI and Frontend Refinements
 *
 * @package    KS_Bootstrapper
 * @subpackage Theme
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Theme {

    /**
     * Removes the prefix (Category:, Tag:, etc.) from archive titles.
     *
     * The final title is rebuilt rather than the 'get_the_archive_title_prefix'
     * filter being emptied: an empty prefix also drops the <span> core wraps the
     * title in, which would change the markup of every archive at once. Date
     * archives are deliberately left alone — 2026 on its own reads as nothing in
     * particular, so "Year: <span>2026</span>" stays as WordPress built it.
     *
     * @param string $title The original archive title.
     *
     * @return string The cleaned archive title, or the original one when the tweak is disabled.
     */
    #[Hook( 'get_the_archive_title' )]
    public function clean_archive_title( string $title ): string {
        if ( ! Options::is( 'clean_archive_titles', true ) ) {
            return $title;
        }

        if ( is_category() ) {
            $title = single_cat_title( '', false );
        } elseif ( is_tag() ) {
            $title = single_tag_title( '', false );
        } elseif ( is_author() ) {
            $title = '<span class="vcard">' . get_the_author() . '</span>';
        } elseif ( is_post_type_archive() ) {
            $title = post_type_archive_title( '', false );
        } elseif ( is_tax() ) {
            $title = single_term_title( '', false );
        }

        return $title;
    }

}
