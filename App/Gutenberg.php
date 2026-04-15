<?php
/**
 * Gutenberg & Block Editor Optimization
 *
 * @package    WP_Bootstrapper
 * @subpackage Core
 * @author     Konstantin Sorokin
 */

namespace WPB;

use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Gutenberg {

    /**
     * SVG filters used for Duotone effects from the frontend <body>.
     */
    #[Hook( 'wp_body_open' )]
    public function remove_svg_filters(): void {
        ! Options::is( 'gutenberg_remove_svg_filters' ) && remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
    }

    /**
     * Duotone SVG filters from the admin header.
     */
    #[Hook( 'in_admin_header' )]
    public function remove_admin_svg_filters(): void {
        ! Options::is( 'gutenberg_remove_admin_svg_filters' ) && remove_action( 'in_admin_header', 'wp_global_styles_render_svg_filters' );
    }

    /**
     * Loading of Global Styles (theme.json) CSS on the frontend.
     */
    #[Hook( 'wp_enqueue_scripts', priority: 10 )]
    public function remove_global_styles(): void {
        ! Options::is( 'gutenberg_remove_global_styles_css' ) && remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    }

    /**
     * Duotone support for blocks during rendering.
     */
    #[Hook( 'init' )]
    public function remove_duotone_support(): void {
        ! Options::is( 'gutenberg_remove_duotone_support' ) && remove_filter( 'render_block', 'wp_render_duotone_support' );
    }

    /**
     * The legacy inner container wrapper from Group blocks.
     */
    #[Hook( 'init' )]
    public function remove_group_inner_container(): void {
        ! Options::is( 'gutenberg_remove_group_inner_container' ) && remove_filter( 'render_block', 'wp_restore_group_inner_container' );
    }

    /**
     * Layout-specific CSS classes and inline styles from rendered blocks.
     */
    #[Hook( 'init' )]
    public function remove_layout_support(): void {
        ! Options::is( 'gutenberg_remove_layout_support' ) && remove_filter( 'render_block', 'wp_render_layout_support_flag' );
    }

    /**
     * Forces WordPress to load all block assets at once instead of separate files.
     * Setting to false prevents "separate core block assets" logic.
     */
    #[Hook( 'should_load_separate_core_block_assets' )]
    public function disable_separate_assets(): bool {
        return ! Options::is( 'gutenberg_disable_separate_assets' );
    }

    /**
     * Dequeues various block editor CSS files from the frontend.
     */
    #[Hook( 'wp_enqueue_scripts', priority: 100 )]
    public function dequeue_block_styles(): void {
        // Main block library
        ! Options::is( 'gutenberg_dequeue_library' ) && wp_dequeue_style( 'wp-block-library' );
        // Theme-specific block styles
        ! Options::is( 'gutenberg_dequeue_library_theme' ) && wp_dequeue_style( 'wp-block-library-theme' );
        // Global styles (inline/file)
        ! Options::is( 'gutenberg_dequeue_global_styles' ) && wp_dequeue_style( 'global-styles' );
        // Classic theme styles
        ! Options::is( 'gutenberg_dequeue_classic_theme' ) && wp_dequeue_style( 'classic-theme-styles' );
    }

    /**
     * Removes global styles and SVG filters from the footer.
     */
    #[Hook( 'wp_footer' )]
    public function remove_footer_assets(): void {
        ! Options::is( 'gutenberg_remove_footer_global_styles' ) && remove_action( 'wp_footer', 'wp_add_global_styles', 1 );
        ! Options::is( 'gutenberg_remove_footer_svg_filters' ) && remove_action( 'wp_footer', 'wp_add_global_styles_render_svg_filters', 1 );
    }

    /**
     * Tells WordPress to omit duotone inline styles entirely.
     * Checkbox ON (true) -> return false (Do NOT omit, standard behavior)
     * Checkbox OFF (false) -> return true (Omit styles, cleanup behavior)
     */
    #[Hook( 'wp_omit_duotone_inline_styles' )]
    public function omit_duotone_styles(): bool {
        return Options::is( 'gutenberg_omit_duotone_inline' ) ? false : true;
    }

    /**
     * Disables the core block patterns provided by WordPress.
     * Checkbox ON (true) -> Logic skip (Standard patterns remain)
     * Checkbox OFF (false) -> remove_theme_support (Patterns disabled)
     */
    #[Hook( 'init' )]
    public function disable_block_patterns(): void {
        if ( ! Options::is( 'gutenberg_disable_patterns' ) ) {
            remove_theme_support( 'core-block-patterns' );
        }
    }

}