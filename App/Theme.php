<?php
/**
 * Theme UI and Frontend Refinements
 *
 * @package    WP_Bootstrapper
 * @subpackage Theme
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Theme {

    /**
     * Removes the prefix (Category:, Tag:, etc.) from archive titles.
     *
     * @param string $title The original archive title.
     *
     * @return string The cleaned archive title.
     */
    #[Hook( 'get_the_archive_title' )]
    public function clean_archive_title( string $title ): string {
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