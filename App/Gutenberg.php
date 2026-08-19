<?php
/**
 * Gutenberg & Block Editor Optimization
 *
 * @package    KS_Bootstrapper
 * @subpackage Core
 * @author     Konstantin Sorokin
 */

namespace KonstantinSorokin\Bootstrapper;

use WP_Duotone;
use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Gutenberg {

    /**
     * Loading of Global Styles (theme.json) CSS on the frontend.
     * * WordPress registers the enqueue twice, once for the header and once for
     * the footer pass, so both registrations are dropped here. It has to happen
     * before wp_enqueue_scripts runs, otherwise the styles are already queued.
     */
    #[Hook( 'wp_loaded' )]
    public function remove_global_styles(): void {
        if ( Options::is( 'gutenberg_global_styles_css', true ) ) {
            return;
        }

        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
        remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
    }

    /**
     * Duotone support for blocks during rendering.
     * * Removing it also empties the data the duotone SVG filters and their
     * stylesheet are built from, so nothing duotone reaches the footer either.
     */
    #[Hook( 'init' )]
    public function remove_duotone_support(): void {
        ! Options::is( 'gutenberg_duotone_support', true ) && remove_filter( 'render_block', [ WP_Duotone::class, 'render_duotone_support' ], 10 );
    }

    /**
     * The legacy inner container wrapper from Group blocks.
     */
    #[Hook( 'init' )]
    public function remove_group_inner_container(): void {
        ! Options::is( 'gutenberg_group_inner_container', true ) && remove_filter( 'render_block_core/group', 'wp_restore_group_inner_container', 10 );
    }

    /**
     * Layout-specific CSS classes and inline styles from rendered blocks.
     */
    #[Hook( 'init' )]
    public function remove_layout_support(): void {
        ! Options::is( 'gutenberg_layout_support', true ) && remove_filter( 'render_block', 'wp_render_layout_support_flag' );
    }

    /**
     * Whether WordPress loads a separate stylesheet per rendered block.
     * * Enabled leaves the decision to WordPress, which for classic themes means
     * the per-block, on-demand CSS it turns on by default; disabled forces the
     * single combined block stylesheet instead.
     *
     * @param bool $load Whether separate block assets are loaded.
     *
     * @return bool
     */
    #[Hook( 'should_load_separate_core_block_assets' )]
    public function separate_block_assets( bool $load ): bool {
        if ( Options::is( 'gutenberg_separate_core_block_assets', true ) ) {
            return $load;
        }

        return false;
    }

    /**
     * Dequeues various block editor CSS files from the frontend.
     * * It runs a second time early in the footer, because with on-demand block
     * styles WordPress enqueues the real global stylesheet at wp_footer:1.
     */
    #[Hook( 'wp_enqueue_scripts', priority: 100 )]
    #[Hook( 'wp_footer', priority: 2 )]
    public function dequeue_block_styles(): void {
        // Main block library
        ! Options::is( 'gutenberg_wp_block_library', true ) && wp_dequeue_style( 'wp-block-library' );
        // Theme-specific block styles
        ! Options::is( 'gutenberg_wp_block_library_theme', true ) && wp_dequeue_style( 'wp-block-library-theme' );
        // Global styles (inline/file)
        ! Options::is( 'gutenberg_global_styles', true ) && wp_dequeue_style( 'global-styles' );
        // Classic theme styles
        ! Options::is( 'gutenberg_classic_theme_styles', true ) && wp_dequeue_style( 'classic-theme-styles' );
    }

    /**
     * Disables the core block patterns provided by WordPress.
     * * The support flag has to be gone before core reads it on init, and after
     * the theme had its own say, hence after_setup_theme at a late priority.
     */
    #[Hook( 'after_setup_theme', priority: 20 )]
    public function disable_block_patterns(): void {
        if ( ! Options::is( 'gutenberg_core_block_patterns', true ) ) {
            remove_theme_support( 'core-block-patterns' );
        }
    }

}
