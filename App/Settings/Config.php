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
                        'description' => __( 'Autosave interval in seconds. Set a high value like 99999 to effectively disable frequent autosaves. Leave the field empty, or set it to 0, for the WordPress default of 60 seconds.', 'ks-bootstrapper' ),
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
                        'description'    => __( 'Removes the emoji detection script and styles from the front end, the admin screens and the /embed/ iframe, together with the TinyMCE plugin integration and the feed conversions.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'clean_archive_titles', FieldType::CHECKBOX, [
                        'label'          => 'get_the_archive_title',
                        'label_checkbox' => __( 'Remove archive title prefixes', 'ks-bootstrapper' ),
                        'description'    => __( 'Drops the prefix WordPress puts in front of a category, tag, author, post type or taxonomy archive title, leaving only the name of the archive. Date archives keep theirs: a bare year or month reads as nothing on its own.', 'ks-bootstrapper' ),
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
                        'description'    => __( 'Keeps oEmbed working: the wp-embed.min.js host script, the discovery link in the page head, the oEmbed REST route, and provider lookups for pasted URLs. Unchecking switches all of that off. The REST API discovery link is no longer removed by this option.', 'ks-bootstrapper' ),
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
                    $section->add_field( 'defer_frontend_scripts', FieldType::CHECKBOX, [
                        'label'          => 'strategy=defer',
                        'label_checkbox' => __( 'Defer all front-end scripts', 'ks-bootstrapper' ),
                        'description'    => __( 'Marks every enqueued front-end script as deferred, so that none of them blocks HTML parsing. WordPress keeps a script blocking whenever deferring it would break execution order, so dependencies stay safe. Warning: inline snippets written straight into theme templates still run in document order and may execute before a deferred script they rely on.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
                $tab->add_section( 'system_media', __( 'Media', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'big_image_size_threshold', FieldType::CHECKBOX, [
                        'label'          => 'big_image_size_threshold',
                        'label_checkbox' => __( 'Automatic scaling of large images', 'ks-bootstrapper' ),
                        'description'    => __( 'When enabled, WordPress will downscale images larger than 2560px.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'strip_upload_metadata', FieldType::CHECKBOX, [
                        'label'          => 'EXIF / IPTC',
                        'label_checkbox' => __( 'Strip camera metadata from uploaded photos', 'ks-bootstrapper' ),
                        'description'    => __( 'Removes GPS coordinates, camera identifiers, timestamps and author fields from newly uploaded JPEG files, so a published photo carries no record of where or by whom it was taken. The colour profile is kept and the image is never re-encoded. Warning: this is irreversible for the uploaded file, and photographer credit and copyright fields are lost along with everything else. Files uploaded before this was switched on are not touched.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'webp_subsizes', FieldType::CHECKBOX, [
                        'label'          => 'image_editor_output_format',
                        'label_checkbox' => __( 'Generate WebP sub-sizes for uploads', 'ks-bootstrapper' ),
                        'description'    => __( 'Saves the resized copies of newly uploaded JPEG and PNG images as WebP, typically 25-35% smaller at the same visual quality. The uploaded original keeps its format, and camera HEIC files still become JPEG. Applies to new uploads only — existing media has to be regenerated. Logos and other hard-edged PNG artwork can suffer from lossy WebP, so check them after switching this on.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'image_quality', FieldType::NUMBER, [
                        'label'       => 'wp_editor_set_quality',
                        'description' => __( 'Compression quality for generated image sizes, 1-100. WordPress uses 82 for JPEG and 86 for WebP. Lowering it to around 72 cuts image weight noticeably before artefacts become visible. Leave the field empty, or set it to 0, for the WordPress defaults.', 'ks-bootstrapper' ),
                        'placeholder' => '82',
                        'default'     => '',
                    ] );
                    $section->add_field( 'eager_image_count', FieldType::NUMBER, [
                        'label'       => 'wp_omit_loading_attr_threshold',
                        'description' => __( 'How many of the first images on a page load eagerly instead of lazily. WordPress uses 3. Setting 1 keeps only the largest contentful image eager and lets everything below the fold wait, which usually improves LCP — but set it too low for a template whose hero sits further down and LCP gets worse instead. Leave the field empty, or set it to 0, for the WordPress default.', 'ks-bootstrapper' ),
                        'placeholder' => '3',
                        'default'     => '',
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
                        'label'          => '*.svg',
                        'label_checkbox' => __( 'Uploads for privileged users', 'ks-bootstrapper' ),
                        'description'    => __( 'When enabled, users with the required capability can upload *.svg files. Every upload is parsed and rewritten against an allowlist: scripts, event handlers, animation, embedded documents and off-site references are removed, and a file that cannot be read as plain SVG is refused. Compressed *.svgz files are not accepted. Grant this only to users you trust.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
                $tab->add_section( 'system_performance', __( 'Performance', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'template_enhancement_buffer', FieldType::CHECKBOX, [
                        'label'          => 'wp_should_output_buffer_template_for_enhancement',
                        'label_checkbox' => __( 'Template enhancement output buffer', 'ks-bootstrapper' ),
                        'description'    => __( 'WordPress 7.0 buffers the whole template so that plugins can rewrite the finished HTML. Core switches the buffer on for classic themes in order to hoist block styles into the head, but leaves it on even when that hoisting is never set up, so the response is held in memory and cannot be streamed for no benefit at all. Unchecking skips the buffer whenever nothing is registered to read it, and keeps it the moment something is.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'speculative_loading', FieldType::SELECT, [
                        'label'       => 'wp_speculation_rules_configuration',
                        'description' => __( 'How aggressively the browser loads the next page before it is clicked. WordPress prefetches on pointer-down. Hover prefetch trades a little bandwidth for a faster click; prerendering builds the whole page in advance and is the fastest, but it runs third-party scripts on pages the visitor may never open. Applies to logged-out visitors only.', 'ks-bootstrapper' ),
                        'options'     => [
                            'auto'                   => __( 'WordPress default — prefetch on pointer-down', 'ks-bootstrapper' ),
                            'prefetch_moderate'      => __( 'Prefetch on hover', 'ks-bootstrapper' ),
                            'prerender_conservative' => __( 'Prerender on pointer-down', 'ks-bootstrapper' ),
                            'prerender_moderate'     => __( 'Prerender on hover', 'ks-bootstrapper' ),
                            'off'                    => __( 'Disabled — no speculation rules', 'ks-bootstrapper' ),
                        ],
                        'default'     => 'auto',
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
                            ] )
                            ->add_field( 'disable_browse_happy', FieldType::CHECKBOX, [
                                'label'          => 'browse-happy',
                                'label_checkbox' => __( 'Do not send browser details to WordPress.org', 'ks-bootstrapper' ),
                                'description'    => __( 'Stops the dashboard posting the administrator browser user-agent string to an external service to check whether it is out of date, and removes the resulting nag box. Plugin, theme and core update checks are not affected.', 'ks-bootstrapper' ),
                                'default'        => false,
                            ] )
                            ->add_field( 'frontend_admin_bar', FieldType::CHECKBOX, [
                                'label'          => 'show_admin_bar',
                                'label_checkbox' => __( 'Admin bar on the front end', 'ks-bootstrapper' ),
                                'description'    => __( 'The toolbar shown to logged-in users while they browse the site. It pulls in Dashicons and the admin bar stylesheet — about 80 KB of CSS the public never sees — and pushes the layout down by 32px, which hides real rendering problems from whoever is reviewing the page. The admin area itself is never affected.', 'ks-bootstrapper' ),
                                'default'        => true,
                            ] );
                } );
                $tab->add_section( 'admin_tools', __( 'Tools', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'action_scheduler_menu', FieldType::CHECKBOX, [
                        'label'          => 'admin_menu',
                        'label_checkbox' => __( 'Add an Action Scheduler item to the Tools menu', 'ks-bootstrapper' ),
                        'description'    => __( 'Adds a Tools > Action Scheduler shortcut when the Action Scheduler library is loaded but has registered no menu entry of its own, which is what happens whenever WooCommerce moves the queue screen under WooCommerce > Status. Nothing is added when the library is absent.', 'ks-bootstrapper' ),
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
                        'description'    => __( 'Adds the RSS and comment feed discovery links to the head by declaring the theme support WordPress requires for them. Uncheck to withdraw that support and strip the discovery links, including the category and comment feeds.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'disable_rsd_link', FieldType::CHECKBOX, [
                        'label'          => 'rsd_link',
                        'label_checkbox' => __( 'Removes RSD (Really Simple Discovery) link', 'ks-bootstrapper' ),
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
                    $section->add_field( 'disable_rest_link_tag', FieldType::CHECKBOX, [
                        'label'          => 'rest_output_link_wp_head',
                        'label_checkbox' => __( 'Remove the REST API discovery link', 'ks-bootstrapper' ),
                        'description'    => __( 'Removes &lt;link rel=&quot;https://api.w.org/&quot;&gt; from the head, and with it the JSON alternate link WordPress prints beside it on single posts and pages. The two Link: response headers that point at the same route come from a separate core callback and have their own checkbox below.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'disable_rest_link_header', FieldType::CHECKBOX, [
                        'label'          => 'rest_output_link_header',
                        'label_checkbox' => __( 'Remove REST API discovery headers', 'ks-bootstrapper' ),
                        'description'    => __( 'Stops WordPress from sending two Link: headers pointing at the REST API on every front-end response. Core sends them from a callback of its own, so the checkbox above, which removes the matching &lt;head&gt; link, leaves them in place. Enable only if nothing on the site discovers the REST route from the response headers.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'preconnect_external_hosts', FieldType::CHECKBOX, [
                        'label'          => 'wp_resource_hints',
                        'label_checkbox' => __( 'Preconnect to external asset hosts', 'ks-bootstrapper' ),
                        'description'    => __( 'WordPress only hints DNS resolution for third-party hosts. This also opens the connection and negotiates TLS in advance, which saves a round trip before the first third-party request. Do not enable it if the page pulls from many external domains — every connection the browser opens and never uses is wasted.', 'ks-bootstrapper' ),
                        'default'        => false,
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
                $tab->add_section( 'head_manifest', __( 'Web app manifest', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'web_app_manifest', FieldType::CHECKBOX, [
                        'label'          => 'manifest.json',
                        'label_checkbox' => __( 'Serve a web app manifest', 'ks-bootstrapper' ),
                        'description'    => __( 'Publishes /manifest.json and links it from the head, so the site can be installed as an app: name and icon on an iOS or Android home screen, "Add to Dock" in Safari on macOS, a splash screen while it launches. The document is assembled from WordPress — title, tagline, language and the Site Icon — so there is nothing to keep in step by hand. It does not colour the browser chrome of an ordinary tab.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'manifest_short_name', FieldType::TEXT, [
                        'label'       => 'short_name',
                        'description' => __( 'Name under the icon on a home screen. Left empty, the site title is used — worth filling in only when that title is longer than about twelve characters, which is where home screens start truncating.', 'ks-bootstrapper' ),
                        'default'     => '',
                    ] );
                    $section->add_field( 'manifest_display', FieldType::SELECT, [
                        'label'       => 'display',
                        'description' => __( 'How much browser interface the installed app keeps.', 'ks-bootstrapper' ),
                        'options'     => [
                            'standalone' => __( 'Standalone — its own window, no address bar', 'ks-bootstrapper' ),
                            'minimal-ui' => __( 'Minimal UI — its own window with navigation controls', 'ks-bootstrapper' ),
                            'fullscreen' => __( 'Fullscreen — no interface at all', 'ks-bootstrapper' ),
                            'browser'    => __( 'Browser — an ordinary tab', 'ks-bootstrapper' ),
                        ],
                        'default'     => 'standalone',
                    ] );
                    $section->add_field( 'manifest_theme_color', FieldType::TEXT, [
                        'label'       => 'theme_color',
                        'description' => __( 'Chrome of the installed app window, as a hex colour. Chrome and Android also read it for the browser toolbar of an ordinary tab; Safari 26 reads it nowhere — there the toolbar takes its tint from CSS, the background colour of a fixed or sticky element near the top edge, then body, then html.', 'ks-bootstrapper' ),
                        'placeholder' => '#ffffff',
                        'default'     => '',
                    ] );
                    $section->add_field( 'manifest_background_color', FieldType::TEXT, [
                        'label'       => 'background_color',
                        'description' => __( 'Splash screen shown while the installed app starts, as a hex colour. Set it to the page background: left empty the platform paints white, and the app opens with a white flash before it darkens to the site.', 'ks-bootstrapper' ),
                        'placeholder' => '#ffffff',
                        'default'     => '',
                    ] );
                } );
            } )
            ->add_tab( 'security', __( 'Security', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'security_main', '', '', function ( Section $section ) {
                    $section->add_field( 'content_security_policy', FieldType::CHECKBOX, [
                        'label'          => 'Content Security Policy',
                        'label_checkbox' => __( 'Content Security Policy (CSP)', 'ks-bootstrapper' ),
                        'description'    => __( 'Sends a Content-Security-Policy that keeps resources on this site\'s own origin while allowing the inline scripts and styles WordPress itself prints. Warning: anything served from another host — analytics, embedded video, web fonts, maps — stays blocked until you name it under Additional allowed sources. Switch on report-only mode first and read the browser console before you enforce this.', 'ks-bootstrapper' ),
                        'default'        => false, // Disabled by default: the policy has to be matched to the site's third-party hosts first
                    ] );
                    $section->add_field( 'content_security_policy_report_only', FieldType::CHECKBOX, [
                        'label'          => 'Content-Security-Policy-Report-Only',
                        'label_checkbox' => __( 'Report violations only, do not block', 'ks-bootstrapper' ),
                        'description'    => __( 'Sends the policy as Content-Security-Policy-Report-Only, so browsers log in their console what the policy would have blocked and load it anyway. Use this to find what breaks before you enforce anything. Has no effect unless the Content Security Policy above is enabled.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'content_security_policy_sources', FieldType::TEXT, [
                        'label'       => 'Additional allowed sources',
                        'description' => __( 'Origins the policy should allow besides this site, separated by spaces or commas — for example https://www.googletagmanager.com https://www.youtube.com. They are added to the script, style, image, font, connection and frame rules.', 'ks-bootstrapper' ),
                        'placeholder' => 'https://www.googletagmanager.com',
                        'default'     => '',
                    ] );
                    $section->add_field( 'x_frame_options', FieldType::CHECKBOX, [
                        'label'          => 'Clickjacking Protection',
                        'label_checkbox' => __( 'Disable Iframe Embedding', 'ks-bootstrapper' ),
                        'description'    => __( 'Sends X-Frame-Options: DENY, so the site cannot be loaded inside a frame on any other page. The HTTP header is the only form browsers honour, so no meta tag is sent. The Customizer\'s own preview iframe is exempt.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'block_author_enumeration', FieldType::CHECKBOX, [
                        'label'          => 'Author Enumeration',
                        'label_checkbox' => __( 'Block author enumeration', 'ks-bootstrapper' ),
                        'description'    => __( 'Returns a 404 for guest requests that try to resolve author archives through query vars or canonical redirects, and covers the author feed and embed routes as well. Also answers the users REST route with a 401 for guests and drops authors from the XML sitemap. Author archives may still be advertised by an SEO plugin, so switch author archives off there too.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'restrict_rest_users', FieldType::CHECKBOX, [
                        'label'          => '/wp-json/wp/v2/users',
                        'label_checkbox' => __( 'Require login for the users REST route', 'ks-bootstrapper' ),
                        'description'    => __( 'Answers guest requests to the users collection and to single user records with a 401 instead of publishing the account name and slug. Logged-in editors keep full access, so the block editor is unaffected. Leave unchecked if an anonymous headless front end reads author data from this site.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'disable_xmlrpc', FieldType::CHECKBOX, [
                        'label'          => 'xmlrpc.php',
                        'label_checkbox' => __( 'Disable XML-RPC and pingbacks', 'ks-bootstrapper' ),
                        'description'    => __( 'Turns off every XML-RPC method, including the pingback methods that stay open when only authenticated XML-RPC is switched off, and removes the X-Pingback header. Leave unchecked if a mobile app, Jetpack or a remote publishing tool posts to this site.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'disable_application_passwords', FieldType::CHECKBOX, [
                        'label'          => 'Application Passwords',
                        'label_checkbox' => __( 'Disable Application Passwords', 'ks-bootstrapper' ),
                        'description'    => __( 'Removes the Application Passwords section from user profiles and rejects Basic-auth requests that use them. An application password is a permanent credential that bypasses two-factor authentication and leaves no login-page audit trail. Leave unchecked if an external service authenticates to this site over the REST API.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'generic_login_errors', FieldType::CHECKBOX, [
                        'label'          => 'wp-login.php',
                        'label_checkbox' => __( 'Generic login failure message', 'ks-bootstrapper' ),
                        'description'    => __( 'Replaces the login errors that reveal whether a username or email address exists on this site with one neutral message. Session, cookie and registration notices are left intact.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'limit_login_attempts', FieldType::CHECKBOX, [
                        'label'          => 'wp_login_failed',
                        'label_checkbox' => __( 'Throttle failed login attempts', 'ks-bootstrapper' ),
                        'description'    => __( 'Rejects further login attempts from one address for 15 minutes after 5 failures, then clears itself automatically. Only the direct connection address is counted, so a forwarded-for header cannot be used to lock other people out. Behind a reverse proxy or a CDN every visitor shares one address and would be throttled together, so map the real client address with the ks_bootstrapper_login_throttle_ip filter before enabling there.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'security_headers', FieldType::CHECKBOX, [
                        'label'          => 'Response headers',
                        'label_checkbox' => __( 'Send hardening response headers', 'ks-bootstrapper' ),
                        'description'    => __( 'Adds X-Content-Type-Options: nosniff, Referrer-Policy: strict-origin-when-cross-origin and a restrictive Permissions-Policy to front-end responses. Stops uploaded files being interpreted as another content type and stops full page URLs leaking to third-party scripts.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                    $section->add_field( 'hsts', FieldType::CHECKBOX, [
                        'label'          => 'Strict-Transport-Security',
                        'label_checkbox' => __( 'Force HTTPS in browsers (HSTS)', 'ks-bootstrapper' ),
                        'description'    => __( 'Tells browsers to reach this site over HTTPS only for the next 180 days, on the front end, the login screen and the dashboard. Warning: browsers cache this instruction and it cannot be withdrawn quickly, so an expired or misconfigured certificate makes the site unreachable with no way to click through. Enable it only once HTTPS is in place and the certificate renews reliably. The header is sent only on requests WordPress already treats as secure, so behind a TLS-terminating proxy HTTPS detection has to be configured in wp-config.php first.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
                $tab->add_section( 'security_uploads', __( 'Uploads', 'ks-bootstrapper' ), '', function ( Section $section ) {
                    $section->add_field( 'harden_uploads', FieldType::CHECKBOX, [
                        'label'          => 'Uploads server configuration',
                        'label_checkbox' => __( 'Block PHP execution in the uploads directory', 'ks-bootstrapper' ),
                        'description'    => __( 'Writes and repairs a managed .htaccess and web.config inside wp-content/uploads that refuse to execute PHP files. Only Apache, LiteSpeed and IIS read those files. On nginx, Caddy and FrankenPHP nothing new is written and the same rule has to be added to the server configuration by hand, for example: location ~* /wp-content/uploads/.*\.php$ { deny all; } — but a guard file written earlier still has its read permissions repaired there, so the site can be moved onto Apache without an unreadable .htaccess taking the whole uploads directory down.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'purge_php_from_uploads', FieldType::CHECKBOX, [
                        'label'          => 'Uploads cleanup',
                        'label_checkbox' => __( 'Delete PHP files found in the uploads directory', 'ks-bootstrapper' ),
                        'description'    => __( 'Removes everything the server could execute as PHP — *.php and its numbered variants, *.phtml and *.phar — from wp-content/uploads on activation and once a day, and reports what it deleted in the dashboard. Warning: deletion is irreversible. Blank "Silence is golden" guard files are kept, but a caching or gallery plugin that legitimately writes PHP into uploads will have those files removed. Leave unchecked unless you know that nothing under uploads is supposed to be PHP.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
            } )
            ->add_tab( 'cookies', __( 'Cookies', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'cookies_section', '', '', function ( Section $section ) {
                    $section->add_field( 'set_referer_cookie_for_new_users', FieldType::CHECKBOX, [
                        'label'          => 'cookie "origin"',
                        'label_checkbox' => __( 'Sets a cookie named "origin" for non-admin visitors if it hasn’t been set yet', 'ks-bootstrapper' ),
                        'description'    => __( 'Stores the address a visitor arrived from in an "origin" cookie that expires after one day (HttpOnly, SameSite=Lax), so a later form submission can carry the traffic source. Admin screens are skipped and an existing cookie is never overwritten. This is not a strictly necessary cookie: leave it off unless your privacy notice covers it.', 'ks-bootstrapper' ),
                        'default'        => false,
                    ] );
                } );
            } )
            ->add_tab( 'plugins', __( 'Plugins', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->when( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ), function ( Tab $tab ) {
                    $tab->add_section( 'contact_form_7', 'Contact Form 7', '', function ( Section $section ) {
                        $section->add_field( 'cf7_default_css', FieldType::CHECKBOX, [
                            'label'          => 'wpcf7_load_css',
                            'label_checkbox' => __( 'Contact Form 7 default CSS', 'ks-bootstrapper' ),
                            'description'    => __( 'Loads the Contact Form 7 global stylesheet on the frontend. Uncheck to remove it.', 'ks-bootstrapper' ),
                            'default'        => true,
                        ] );
                        $section->add_field( 'cf7_autop', FieldType::CHECKBOX, [
                            'label'          => 'wpcf7_autop_or_not',
                            'label_checkbox' => __( 'Automatic paragraph wrapping', 'ks-bootstrapper' ),
                            'description'    => __( 'Wraps generated form markup in automatic paragraphs and line breaks. Uncheck to output clean markup.', 'ks-bootstrapper' ),
                            'default'        => true,
                        ] );
                        $section->add_field( 'cf7_referer_page_tag', FieldType::CHECKBOX, [
                            'label'          => 'referer-page',
                            'label_checkbox' => __( 'Fill hidden referer-page form tag', 'ks-bootstrapper' ),
                            'description'    => __( 'Fills a hidden Contact Form 7 tag named "referer-page" with the visitor\'s referrer, when that referrer is an absolute http or https URL. The referrer takes precedence over any value written inside the tag.', 'ks-bootstrapper' ),
                            'default'        => true,
                        ] );
                    } );
                } );
                $tab->when( is_plugin_active( 'translatepress-multilingual/index.php' ), function ( Tab $tab ) {
                    $tab->add_section( 'translatepress', 'TranslatePress', '', function ( Section $section ) {
                        $section->add_field( 'trp_disable_default_css', FieldType::CHECKBOX, [
                            'label'          => 'trp_disable_default_css',
                            'label_checkbox' => __( 'Disable Default TranslatePress CSS', 'ks-bootstrapper' ),
                            'description'    => __( 'Dequeues the TranslatePress language switcher stylesheets: trp-language-switcher-v2 (the default since 3.3) plus the legacy trp-language-switcher-style and trp-floater-language-switcher-style. The V2 switcher also prints inline styles that a stylesheet dequeue cannot remove. Use this if you want to style the switcher manually in your theme.', 'ks-bootstrapper' ),
                            'default'        => false,
                        ] );
                    } );
                } );
            } )
            ->add_tab( 'gutenberg', __( 'Gutenberg', 'ks-bootstrapper' ), function ( Tab $tab ) {
                $tab->add_section( 'gutenberg_main', '', '', function ( Section $section ) {
                    $section->add_field( 'gutenberg_global_styles_css', FieldType::CHECKBOX, [
                        'label'          => 'wp_enqueue_global_styles',
                        'label_checkbox' => __( 'Global Styles Enqueue', 'ks-bootstrapper' ),
                        'description'    => __( 'Loads the Global Styles (theme.json) CSS on the front end. Unchecking cancels both enqueues WordPress registers for it, the one in the head and the one in the footer pass.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_duotone_support', FieldType::CHECKBOX, [
                        'label'          => 'WP_Duotone::render_duotone_support',
                        'label_checkbox' => __( 'Duotone filter rendering', 'ks-bootstrapper' ),
                        'description'    => __( 'Renders duotone filters for blocks that use them. Unchecking also keeps the duotone SVG definitions and their stylesheet out of the footer, because nothing collects duotone data any more.', 'ks-bootstrapper' ),
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
                        'description'    => __( 'Loads only the CSS of the blocks actually present on the page instead of one combined stylesheet. WordPress 7.0 enables this by default for classic themes; unchecking forces the single combined stylesheet and turns off on-demand block styles.', 'ks-bootstrapper' ),
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
                        'description'    => __( 'CSS generated from theme.json settings. Unchecking dequeues the stylesheet in the head and again early in the footer, where WordPress 7.0 enqueues it for classic themes.', 'ks-bootstrapper' ),
                        'default'        => true,
                    ] );
                    $section->add_field( 'gutenberg_classic_theme_styles', FieldType::CHECKBOX, [
                        'label'          => 'classic-theme-styles',
                        'label_checkbox' => __( 'Classic Theme Styles', 'ks-bootstrapper' ),
                        'description'    => __( 'Legacy styles for block elements in classic (non-block) themes.', 'ks-bootstrapper' ),
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
            ->retire(
                'disable_wlwmanifest_link',
                'gutenberg_svg_filters',
                'gutenberg_admin_svg_filters',
                'gutenberg_wp_global_styles',
                'gutenberg_wp_global_styles_render_svg_filters',
                'gutenberg_omit_duotone_inline'
            )
            ->boot(); // Register the hooks in WordPress
    }

}
