<?php
/**
 * Class: Head
 *
 * @package    KS_Bootstrapper
 * @subpackage Head
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Head {

    /**
     * Cleans up unnecessary and potentially insecure meta tags from the <head>.
     * * Hooked to 'init' so it executes after WordPress core has added its default actions,
     * but before the page actually starts rendering.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function clean_head(): void {
        // Removes the WordPress version
        Options::is( 'disable_wp_generator', true ) && remove_action( 'wp_head', 'wp_generator' );

        // Removes RSD (Really Simple Discovery)
        Options::is( 'disable_rsd_link', true ) && remove_action( 'wp_head', 'rsd_link' );

        // Removes the short link from head and HTTP headers
        if ( Options::is( 'disable_wp_shortlink', true ) ) {
            remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
            remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        }

        // Removes the REST API discovery link, and with it the JSON alternate link
        // printed on singular views. The matching Link: headers come from a separate
        // core callback and are governed by their own setting.
        Options::is( 'disable_rest_link_tag' ) && remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
    }

    /**
     * Turns RSS feed discovery links in the <head> on or off.
     * * Core registers feed_links() and feed_links_extra() unconditionally, but both
     * return early unless the active theme declares 'automatic-feed-links' support.
     * Removing callbacks can therefore only ever take links away, so the enabled
     * branch has to declare that theme support itself.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function feed_links(): void {
        if ( Options::is( 'automatic_feed_links', true ) ) {
            add_theme_support( 'automatic-feed-links' );

            return;
        }

        remove_theme_support( 'automatic-feed-links' );

        // Additionally, we remove links to category and comment feeds
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'feed_links', 2 );
    }

    /**
     * Removes emoji assets and related integrations.
     */
    #[Hook( 'init' )]
    public function disable_emojis(): void {
        if ( ! Options::is( 'disable_emojis', true ) ) {
            return;
        }

        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );

        // The /embed/ iframe is a second, independent surface: core prints the
        // detection script from 'embed_head' and flushes the deferred payload through
        // 'embed_footer', so the wp_head removal above never reaches it. The two
        // wp_enqueue_emoji_styles copies already abstain once print_emoji_styles is
        // unhooked, and are dropped here so the suppression does not rely on that.
        remove_action( 'embed_head', 'print_emoji_detection_script' );
        remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );

        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    }

    /**
     * Removes the emoji plugin from TinyMCE.
     *
     * @param array $plugins TinyMCE plugin list.
     *
     * @return array
     */
    #[Hook( 'tiny_mce_plugins' )]
    public function disable_emojis_tinymce( array $plugins ): array {
        if ( ! Options::is( 'disable_emojis', true ) ) {
            return $plugins;
        }

        return array_values( array_diff( $plugins, [ 'wpemoji' ] ) );
    }

    /**
     * Removes Recent Comments widget inline styles from the document head.
     */
    #[Hook( 'widgets_init' )]
    public function remove_recent_comments_style(): void {
        if ( ! Options::is( 'disable_recent_comments_style', true ) ) {
            return;
        }

        global $wp_widget_factory;

        if (
            isset( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] ) &&
            is_object( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] )
        ) {
            remove_action(
                'wp_head',
                [ $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ]
            );
        }
    }

    /**
     * Disables automatic phone number detection on iOS devices.
     * Adds a meta tag to prevent Safari from automatically turning
     * phone-like numbers into clickable links.
     */
    #[Hook( 'wp_head', priority: 5 )]
    public function phone_detection(): void {
        if ( Options::is( 'phone_detection', true ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<meta name="format-detection" content="telephone=no">' . PHP_EOL;
        }
    }

    /**
     * Defines the geographical location of the content.
     * Helpful for local SEO to specify country and region (e.g., UA-KH).
     */
    #[Hook( 'wp_head', 5 )]
    public function geo_region(): void {
        $region = Options::get( 'geo_region' );

        if ( ! empty( $region ) ) {
            printf(
                '<meta name="geo.region" content="%s">%s',
                esc_attr( $region ),
                PHP_EOL
            );
        }
    }

    /**
     * Adds the clickjacking and Content-Security-Policy headers to the response.
     * * Runs on 'wp_headers' rather than on the later 'send_headers' action on purpose.
     * The Customizer relaxes both headers for its preview iframe through the very same
     * filter at the default priority, so answering earlier lets core keep the last word
     * and the theme preview pane still loads.
     *
     * @param array $headers Associative array of headers to be sent.
     *
     * @return array
     */
    #[Hook( 'wp_headers', priority: 5 )]
    public function security_headers( array $headers ): array {
        if ( Options::is( 'x_frame_options', true ) ) {
            $headers['X-Frame-Options'] = 'DENY';
        }

        if ( Options::is( 'content_security_policy' ) ) {
            $name = Options::is( 'content_security_policy_report_only' )
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $headers[ $name ] = $this->content_security_policy_value();
        }

        return $headers;
    }

    /**
     * Repeats the Content-Security-Policy as a meta tag.
     * * Browsers honour the policy in a meta tag, which keeps it working on hosts and
     * proxies that strip unknown response headers. Report-only mode has no meta form,
     * so the tag is withheld there instead of quietly enforcing what should only be
     * reported.
     */
    #[Hook( 'wp_head', priority: 1 )]
    public function content_security_policy(): void {
        if ( ! Options::is( 'content_security_policy' ) || Options::is( 'content_security_policy_report_only' ) ) {
            return;
        }

        printf(
            '<meta http-equiv="Content-Security-Policy" content="%s">%s',
            esc_attr( $this->content_security_policy_value() ),
            PHP_EOL
        );
    }

    /**
     * Builds the Content-Security-Policy the site is served with.
     * * The baseline is deliberately one a stock WordPress survives: core, the block
     * editor and most plugins print inline <style> and <script> blocks, so both are
     * allowed. Everything hosted elsewhere — analytics, embeds, web fonts — has to be
     * named in the additional sources setting or added through the filter below.
     *
     * @return string
     */
    private function content_security_policy_value(): string {
        $extra = $this->content_security_policy_sources();
        $self  = [ "'self'" ];

        $directives = [
            'default-src' => $self,
            'script-src'  => array_merge( $self, [ "'unsafe-inline'" ], $extra ),
            'style-src'   => array_merge( $self, [ "'unsafe-inline'" ], $extra ),
            'img-src'     => array_merge( $self, [ 'data:' ], $extra ),
            'font-src'    => array_merge( $self, [ 'data:' ], $extra ),
            'connect-src' => array_merge( $self, $extra ),
            'frame-src'   => array_merge( $self, $extra ),
            'base-uri'    => $self,
            'form-action' => $self,
        ];

        $policy = [];

        foreach ( $directives as $directive => $sources ) {
            $policy[] = $directive . ' ' . implode( ' ', $sources );
        }

        /**
         * Filters the Content-Security-Policy sent by the plugin.
         *
         * @param string $policy The complete policy, directives separated by semicolons.
         */
        return (string) apply_filters( 'ks_bootstrapper_csp', implode( '; ', $policy ) );
    }

    /**
     * Reads the extra origins the site owner allowed in the settings.
     * * Values are separated by spaces, commas or line breaks. Semicolons are dropped
     * so that a stray one cannot smuggle an extra directive into the policy.
     *
     * @return array
     */
    private function content_security_policy_sources(): array {
        $raw = trim( str_replace( ';', ' ', (string) Options::get( 'content_security_policy_sources', '' ) ) );

        if ( '' === $raw ) {
            return [];
        }

        return array_values( array_filter( (array) preg_split( '/[\s,]+/', $raw ) ) );
    }

    /**
     * Disables wptexturize conversions when requested in settings.
     *
     * @param bool $run Whether wptexturize should run.
     *
     * @return bool
     */
    #[Hook( 'run_wptexturize' )]
    public function run_wptexturize( bool $run ): bool {
        return Options::is( 'disable_wptexturize' ) ? false : $run;
    }

}
