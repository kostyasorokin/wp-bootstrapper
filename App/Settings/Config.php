<?php
/**
 * Plugin Settings Configuration
 *
 * Registers and configures the main settings page for the KS Bootstrapper plugin,
 * using a fluent builder interface to define tabs, sections, and fields.
 *
 * @package    KS_Bootstrapper
 * @subpackage Settings
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper\Settings;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Enums\FieldType;
use KonstantinSorokin\Bootstrapper\Settings\Definitions\Section;
use KonstantinSorokin\Bootstrapper\Settings\Definitions\Tab;

defined( 'ABSPATH' ) || exit;

class Config {

    /**
     * Registers the settings page configuration.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function register(): void {
        // Include plugin functions if not already available to use is_plugin_active()
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        Settings::make(
            optionName: 'ks_bootstrapper_options',
            pageId: 'bootstrapper',
            title: __( 'Bootstrapper', 'ks-bootstrapper' )
        )
            ->add_tab( 'system', __( 'System', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'system_cron', __( 'Cron', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'disable_wp_cron', FieldType::CHECKBOX, [
                        'label'          => 'DISABLE_WP_CRON',
                        'label_checkbox' => __( 'Disable WordPress Virtual Cron', 'ks-bootstrapper' ),
                        'description'    => __( 'Disables wp-cron.php execution on every page load. Recommended if you have a real system cron job configured.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
                $tab->add_section( 'system_content', __( 'Content', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'disable_post_revisions', FieldType::CHECKBOX, [
                        'label'          => 'WP_POST_REVISIONS',
                        'label_checkbox' => __( 'Disable post revisions', 'ks-bootstrapper' ),
                        'description'    => __( 'Prevents WordPress from storing post revision history.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'autosave_interval', FieldType::NUMBER, [
                        'label'       => 'AUTOSAVE_INTERVAL',
                        'description' => __( 'Autosave interval in seconds. Set a high value like 99999 to effectively disable frequent autosaves.', 'ks-bootstrapper' ),
                        'placeholder' => '99999',
                        'default'     => 99999,
                    ] );
                    $section->add_field( 'disable_wptexturize', FieldType::CHECKBOX, [
                        'label'          => 'run_wptexturize',
                        'label_checkbox' => __( 'Disable wptexturize', 'ks-bootstrapper' ),
                        'description'    => __( 'Stops WordPress from converting plain quotes, dashes, and similar characters into typographic variants.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'disable_emojis', FieldType::CHECKBOX, [
                        'label'          => 'emoji',
                        'label_checkbox' => __( 'Disable emojis', 'ks-bootstrapper' ),
                        'description'    => __( 'Removes emoji scripts, styles, TinyMCE plugin integration, feed conversions, and emoji DNS prefetch hints.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                } );
                $tab->add_section( 'system_javascript', __( 'JavaScript', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'jquery', FieldType::CHECKBOX, [
                        'label'          => 'jquery.min.js',
                        'label_checkbox' => 'jQuery',
                        'description'    => __( 'Warning: Removes jQuery from the frontend. Only use this if your theme and plugins do not rely on jQuery. This will significantly improve PageSpeed, but might break things.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'jquery_migrate', FieldType::CHECKBOX, [
                        'label'          => 'jquery-migrate.js',
                        'label_checkbox' => 'jQuery Migrate',
                        'description'    => __( 'The migration layer for jQuery. Warning: This may break very old themes or plugins that rely on deprecated jQuery functions.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'oembed_full', FieldType::CHECKBOX, [
                        'label'          => 'oEmbed API',
                        'label_checkbox' => __( 'oEmbed features', 'ks-bootstrapper' ),
                        'description'    => __( 'wp-embed.min.js, discovery links (REST API/XML-RPC), and API routes.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'autosave_script', FieldType::CHECKBOX, [
                        'label'          => 'autosave',
                        'label_checkbox' => __( 'Autosave script', 'ks-bootstrapper' ),
                        'description'    => __( 'The WordPress autosave script on both frontend and admin print-script hooks.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'heartbeat_api', FieldType::CHECKBOX, [
                        'label'          => 'Heartbeat API',
                        'label_checkbox' => 'Heartbeat API',
                        'description'    => __( 'The Heartbeat API script (wp-heartbeat.min.js). This saves server resources but disables features like auto-saving and post-lock notifications.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                } );
                $tab->add_section( 'system_media', __( 'Media', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'big_image_size_threshold', FieldType::CHECKBOX, [
                        'label'          => 'big_image_size_threshold',
                        'label_checkbox' => __( 'Automatic scaling of large images', 'ks-bootstrapper' ),
                        'description'    => __( 'When enabled, WordPress will downscale images larger than 2560px.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
                $tab->add_section( 'system_files', __( 'Files', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'disallow_file_mods', FieldType::CHECKBOX, [
                        'label'          => 'DISALLOW_FILE_MODS',
                        'label_checkbox' => __( 'Disable File Modifications', 'ks-bootstrapper' ),
                        'description'    => __( 'Completely disables the built-in WordPress theme and plugin editor, as well as the ability to update or install new plugins and themes from the admin dashboard. Highly recommended for production sites.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'allow_svg_uploads', FieldType::CHECKBOX, [
                        'label'          => '*.svg; *.svgz',
                        'label_checkbox' => __( 'Uploads for privileged users', 'ks-bootstrapper' ),
                        'description'    => __( 'When enabled, only users with the required capability can upload *.svg; *.svgz files, and uploaded files are server-side validated.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
            } )
            ->add_tab( 'admin', __( 'Admin', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'admin_cleanup', '', '', function ( Section $section ) {
                    $section->add_field( 'disable_admin_bar_menu_logo', FieldType::CHECKBOX, [
                        'label'          => 'admin_bar_menu',
                        'label_checkbox' => __( 'Removes the WordPress logo from the admin bar', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] )
                            ->add_field( 'disable_welcome_panel', FieldType::CHECKBOX, [
                                'label'          => 'welcome_panel',
                                'label_checkbox' => __( 'Remove the Welcome Panel', 'ks-bootstrapper' ),
                                'description'    => __( 'Hides the "Welcome to WordPress" box from the dashboard.', 'ks-bootstrapper' ),
                                'default'        => true,
                            ] )
                            ->add_field( 'disable_admin_footer_text', FieldType::CHECKBOX, [
                                'label'          => 'admin_footer_text',
                                'label_checkbox' => __( 'Remove "Thank you for creating with WordPress" text', 'ks-bootstrapper' ),
                                'default'        => true,
                            ] )
                            ->add_field( 'disable_admin_footer_version', FieldType::CHECKBOX, [
                                'label'          => 'update_footer',
                                'label_checkbox' => __( 'Remove WordPress version from the footer', 'ks-bootstrapper' ),
                                'default'        => true,
                            ] );
                } );
            } )
            ->add_tab( 'head', __( 'Head', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'head_cleanup', '', '', function ( Section $section ) {
                    $section->add_field( 'disable_wp_generator', FieldType::CHECKBOX, [
                        'label'          => 'wp_generator',
                        'label_checkbox' => __( 'Removes the WordPress version tag', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'automatic_feed_links', FieldType::CHECKBOX, [
                        'label'          => 'automatic_feed_links',
                        'label_checkbox' => __( 'RSS Feed Links', 'ks-bootstrapper' ),
                        'description'    => __( 'Adds links to RSS feeds directly into the head. Uncheck this if you want to remove RSS discovery links from your site.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'disable_rsd_link', FieldType::CHECKBOX, [
                        'label'          => 'rsd_link',
                        'label_checkbox' => __( 'Removes RSD (Really Simple Discovery) link', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'disable_wlwmanifest_link', FieldType::CHECKBOX, [
                        'label'          => 'wlwmanifest_link',
                        'label_checkbox' => __( 'Removes Windows Live Writer manifest link', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'disable_recent_comments_style', FieldType::CHECKBOX, [
                        'label'          => 'recent_comments_style',
                        'label_checkbox' => __( 'Remove Recent Comments widget inline styles', 'ks-bootstrapper' ),
                        'description'    => __( 'Stops WordPress from injecting Recent Comments widget styles into the document head.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'disable_wp_shortlink', FieldType::CHECKBOX, [
                        'label'          => 'wp_shortlink',
                        'label_checkbox' => __( 'Removes the short link for the current page', 'ks-bootstrapper' ),
                        'description'    => __( 'Removes rel="shortlink" from the &lt;head&gt; and the Link header from server responses.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'phone_detection', FieldType::CHECKBOX, [
                        'label'          => 'iOS Phone Detection',
                        'label_checkbox' => __( 'Disable automatic phone detection', 'ks-bootstrapper' ),
                        'description'    => __( 'Adds a meta tag to prevent iOS Safari from automatically linking phone numbers:', 'ks-bootstrapper' ) . ' &lt;meta name=&quot;format-detection&quot; content=&quot;telephone=no&quot;&gt;',
                        'default'        => true,
                    ] );
                    $section->add_field( 'geo_region', FieldType::TEXT, [
                        'label'       => 'geo.region',
                        'description' => __( 'Specify the geographical region (e.g., UA-KH):', 'ks-bootstrapper' ) . ' &lt;meta name=&quot;geo.region&quot; content=&quot;...&quot;&gt;',
                        'placeholder' => 'UA-KH',
                        'default'     => '',
                    ] );
                } );
            } )
            ->add_tab( 'security', __( 'Security', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'security_main', '', '', function ( Section $section ) {
                    $section->add_field( 'content_security_policy', FieldType::CHECKBOX, [
                        'label'          => 'Content Security Policy',
                        'label_checkbox' => __( 'Strict Content Security Policy (CSP)', 'ks-bootstrapper' ),
                        'description'    => __( 'Protects your site by allowing resources only from your own domain. Sends an HTTP header and a meta tag:', 'ks-bootstrapper' ) . ' &lt;meta http-equiv=&quot;Content-Security-Policy&quot; content=&quot;default-src &#39;self&#39;&quot;&gt;',
                        'default'        => false, // Disabled by default, as this is a "strict" measure
                    ] );
                    $section->add_field( 'x_frame_options', FieldType::CHECKBOX, [
                        'label'          => 'Clickjacking Protection',
                        'label_checkbox' => __( 'Disable Iframe Embedding', 'ks-bootstrapper' ),
                        'description'    => __( 'Protects your site by sending an HTTP header and a meta tag.', 'ks-bootstrapper' ) . ' http header: X-Frame-Options: DENY +  &lt;meta http-equiv=&quot;X-Frame-Options&quot; content=&quot;DENY&quot;&gt;',
                        'default'        => true,
                    ] );
                    $section->add_field( 'block_author_enumeration', FieldType::CHECKBOX, [
                        'label'          => 'Author Enumeration',
                        'label_checkbox' => __( 'Block author enumeration', 'ks-bootstrapper' ),
                        'description'    => __( 'Returns a 404 response for guest requests that try to resolve author archives via query vars or canonical redirects.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
            } )
            ->add_tab( 'cookies', __( 'Cookies', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'cookies_section', '', '', function ( Section $section ) {
                    $section->add_field( 'set_referer_cookie_for_new_users', FieldType::CHECKBOX, [
                        'label'          => 'cookie "origin"',
                        'label_checkbox' => __( 'Sets a cookie named "origin" for non-admin visitors if it hasn’t been set yet', 'ks-bootstrapper' ),
                    ] );
                } );
            } )
            ->add_tab( 'plugins', __( 'Plugins', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->when( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ), function ( Tab $tab ) {
                    $tab->add_section( 'contact_form_7', 'Contact Form 7', '', function ( Section $section ) {
                        $section->add_field( 'cf7_default_css', FieldType::CHECKBOX, [
                            'label'          => 'wpcf7_load_css',
                            'label_checkbox' => __( 'Contact Form 7 default CSS', 'ks-bootstrapper' ),
                            'description'    => __( 'Prevents Contact Form 7 from loading its global stylesheet.', 'ks-bootstrapper' ),
                            'default'        => true,
                        ] );
                        $section->add_field( 'cf7_autop', FieldType::CHECKBOX, [
                            'label'          => 'wpcf7_autop_or_not',
                            'label_checkbox' => __( 'Automatic paragraph wrapping', 'ks-bootstrapper' ),
                            'description'    => __( 'Stops Contact Form 7 from wrapping generated markup in automatic paragraphs and line breaks.', 'ks-bootstrapper' ),
                            'default'        => true,
                        ] );
                        $section->add_field( 'cf7_referer_page_tag', FieldType::CHECKBOX, [
                            'label'          => 'referer-page',
                            'label_checkbox' => __( 'Fill hidden referer-page form tag', 'ks-bootstrapper' ),
                            'description'    => __( 'Populates a Contact Form 7 form tag named "referer-page" with the current validated referrer URL.', 'ks-bootstrapper' ),
                            'default'        => true,
                        ] );
                    } );
                } );
                $tab->when( is_plugin_active( 'translatepress-multilingual/index.php' ), function ( Tab $tab ) {
                    $tab->add_section( 'translatepress', 'TranslatePress', '', function ( Section $section ) {
                        $section->add_field( 'trp_disable_default_css', FieldType::CHECKBOX, [
                            'label'          => 'trp_disable_default_css',
                            'label_checkbox' => __( 'Disable Default TranslatePress CSS', 'ks-bootstrapper' ),
                            'description'    => __( 'Removes trp-language-switcher-style. Use this if you want to style the switcher manually in your theme.', 'ks-bootstrapper' ),
                            'default'        => false,
                        ] );
                    } );
                } );
            } )
            ->add_tab( 'gutenberg', __( 'Gutenberg', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'gutenberg_main', '', '', function ( Section $section ) {
                    $section->add_field( 'gutenberg_svg_filters', FieldType::CHECKBOX, [
                        'label'          => 'wp_global_styles_render_svg_filters',
                        'label_checkbox' => __( 'Duotone SVG filters from wp_body_open', 'ks-bootstrapper' ),
                        'description'    => __( 'Renders SVG filters used for duotone effects immediately after the body tag.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_admin_svg_filters', FieldType::CHECKBOX, [
                        'label'          => 'wp_global_styles_render_svg_filters',
                        'label_checkbox' => __( 'Duotone SVG filters from in_admin_header', 'ks-bootstrapper' ),
                        'description'    => __( 'Renders SVG filters in the WordPress admin bar / header area.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_global_styles_css', FieldType::CHECKBOX, [
                        'label'          => 'wp_enqueue_global_styles',
                        'label_checkbox' => __( 'Global Styles Enqueue', 'ks-bootstrapper' ),
                        'description'    => __( 'Loads the main Global Styles (theme.json) CSS on the frontend.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_duotone_support', FieldType::CHECKBOX, [
                        'label'          => 'wp_render_duotone_support',
                        'label_checkbox' => __( 'Duotone filter Rendering', 'ks-bootstrapper' ),
                        'description'    => __( 'Support for rendering duotone filters for blocks.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_group_inner_container', FieldType::CHECKBOX, [
                        'label'          => 'wp_restore_group_inner_container',
                        'label_checkbox' => __( 'Group Inner Container', 'ks-bootstrapper' ),
                        'description'    => __( 'Restores the legacy .wp-block-group__inner-container wrapper for Group blocks.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_layout_support', FieldType::CHECKBOX, [
                        'label'          => 'wp_render_layout_support_flag',
                        'label_checkbox' => __( 'Layout Support Flag', 'ks-bootstrapper' ),
                        'description'    => __( 'Adds layout-specific CSS classes and inline styles to blocks.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_separate_core_block_assets', FieldType::CHECKBOX, [
                        'label'          => 'should_load_separate_core_block_assets',
                        'label_checkbox' => __( 'Separate core block assets loading', 'ks-bootstrapper' ),
                        'description'    => __( 'Loads only the CSS for the blocks present on the page instead of one giant file.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_wp_block_library', FieldType::CHECKBOX, [
                        'label'          => 'wp-block-library',
                        'label_checkbox' => __( 'Block Library CSS', 'ks-bootstrapper' ),
                        'description'    => __( 'The core CSS for all standard WordPress blocks.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_wp_block_library_theme', FieldType::CHECKBOX, [
                        'label'          => 'wp-block-library-theme',
                        'label_checkbox' => __( 'Block Library Theme', 'ks-bootstrapper' ),
                        'description'    => __( 'Visual styles for core blocks that make them look like the default theme.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_global_styles', FieldType::CHECKBOX, [
                        'label'          => 'global-styles',
                        'label_checkbox' => __( 'Global Styles CSS', 'ks-bootstrapper' ),
                        'description'    => __( 'CSS generated from theme.json settings.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_classic_theme_styles', FieldType::CHECKBOX, [
                        'label'          => 'classic-theme-styles',
                        'label_checkbox' => __( 'Classic Theme Styles', 'ks-bootstrapper' ),
                        'description'    => __( 'Legacy styles for block elements in classic (non-block) themes.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_wp_global_styles', FieldType::CHECKBOX, [
                        'label'          => 'wp_add_global_styles',
                        'label_checkbox' => __( 'Footer Global Styles', 'ks-bootstrapper' ),
                        'description'    => __( 'Inline global styles normally added to the footer.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_wp_global_styles_render_svg_filters', FieldType::CHECKBOX, [
                        'label'          => 'wp_add_global_styles_render_svg_filters',
                        'label_checkbox' => __( 'Footer SVG Filters', 'ks-bootstrapper' ),
                        'description'    => __( 'Renders remaining SVG filters in the site footer.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_omit_duotone_inline', FieldType::CHECKBOX, [
                        'label'          => 'wp_omit_duotone_inline_styles',
                        'label_checkbox' => __( 'Omit Inline Duotone', 'ks-bootstrapper' ),
                        'description'    => __( 'By default, WP prevents some duotone styles from being inline. Keep enabled for default behavior.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_core_block_patterns', FieldType::CHECKBOX, [
                        'label'          => 'core-block-patterns',
                        'label_checkbox' => __( 'Block Patterns', 'ks-bootstrapper' ),
                        'description'    => __( 'The core block pattern library provided by WordPress.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                } );
            } )
            ->boot(); // Register the hooks in WordPress
    }

}
