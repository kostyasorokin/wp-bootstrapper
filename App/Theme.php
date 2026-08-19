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
     * Removes the prefix (Category:, Tag:, Author:, Year:, etc.) from archive titles.
     *
     * Filtering the prefix covers every archive type WordPress prefixes, date
     * archives included, and leaves the markup core builds around the title
     * untouched.
     *
     * @param string $prefix The original archive title prefix.
     *
     * @return string An empty prefix, or the original one when the tweak is disabled.
     */
    #[Hook( 'get_the_archive_title_prefix' )]
    public function clean_archive_title_prefix( string $prefix ): string {
        return Options::is( 'clean_archive_titles', true ) ? '' : $prefix;
    }

}