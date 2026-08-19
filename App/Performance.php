<?php
/**
 * Front-end Performance and Delivery
 *
 * @package    KS_Bootstrapper
 * @subpackage Performance
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery-level tweaks: template output buffering, speculative loading,
 * script loading strategies, resource hints, response headers and image sub-sizes.
 * * Every option here defaults to the behaviour WordPress ships with, so the
 * class is inert until a checkbox is ticked.
 */
class Performance {

    /**
     * Hosts collected during the dns-prefetch pass of wp_resource_hints().
     *
     * @var array<string, true>
     */
    private array $prefetch_hosts = [];

    /**
     * The full-template output buffer introduced in WordPress 6.9.
     * * Core opens the buffer only when something is registered to read the finished
     * document, but classic themes get the flag forced on at priority 0 even when the
     * block-style hoisting that needs it never gets set up. The result is the whole
     * response held in memory with flushing disabled and nobody consuming it.
     *
     * @param bool $use_buffer Whether an output buffer is started.
     *
     * @return bool
     */
    #[Hook( 'wp_should_output_buffer_template_for_enhancement', priority: 99 )]
    public function template_enhancement_buffer( bool $use_buffer ): bool {
        if ( ! $use_buffer || Options::is( 'template_enhancement_buffer', true ) ) {
            return $use_buffer;
        }

        // Core asks the same question while it registers its block style hooks.
        // Answering there would switch on-demand block styles off as a side effect,
        // so the decision is left to the real one, taken just before the template loads.
        if ( doing_action( 'wp_default_styles' ) ) {
            return $use_buffer;
        }

        // Keep the buffer whenever something will actually consume it. The "started"
        // action is the only signal available at decision time, because core's own
        // consumer registers its filter after the buffer has opened.
        if (
            has_filter( 'wp_template_enhancement_output_buffer' ) ||
            has_action( 'wp_finalized_template_enhancement_output_buffer' ) ||
            has_action( 'wp_template_enhancement_output_buffer_started' )
        ) {
            return $use_buffer;
        }

        return false;
    }

    /**
     * Speculative loading (prefetch/prerender) configuration.
     * * WordPress prefetches on pointer-down for logged-out visitors. A null
     * configuration means core has already disabled speculation for this request,
     * and that decision is never overridden here.
     *
     * @param array|null $config Associative array with 'mode' and 'eagerness' keys, or null.
     *
     * @return array|null
     */
    #[Hook( 'wp_speculation_rules_configuration' )]
    public function speculation_rules( ?array $config ): ?array {
        $mode = (string) Options::get( 'speculative_loading', 'auto' );

        if ( 'off' === $mode ) {
            return null;
        }

        // Logged-in visitors and sites without pretty permalinks stay untouched.
        if ( null === $config ) {
            return $config;
        }

        return match ( $mode ) {
            'prefetch_moderate'      => [ 'mode' => 'prefetch', 'eagerness' => 'moderate' ],
            'prerender_conservative' => [ 'mode' => 'prerender', 'eagerness' => 'conservative' ],
            'prerender_moderate'     => [ 'mode' => 'prerender', 'eagerness' => 'moderate' ],
            default                  => $config,
        };
    }

    /**
     * Marks every front-end script as deferred and lets core decide what is eligible.
     * * WordPress downgrades the strategy back to blocking for any handle whose
     * dependents or "after" inline scripts require synchronous execution.
     *
     * @param array $handles Script dependency handles left to print.
     *
     * @return array
     */
    #[Hook( 'print_scripts_array' )]
    public function defer_frontend_scripts( array $handles ): array {
        if ( is_admin() || is_customize_preview() || ! Options::is( 'defer_frontend_scripts' ) ) {
            return $handles;
        }

        $scripts = wp_scripts();

        foreach ( $handles as $handle ) {
            // Alias handles carry no source, and add_data() rejects a strategy for them.
            if ( empty( $scripts->registered[ $handle ]->src ) ) {
                continue;
            }

            // A strategy chosen at registration time always wins.
            if ( '' === (string) $scripts->get_data( $handle, 'strategy' ) ) {
                $scripts->add_data( $handle, 'strategy', 'defer' );
            }
        }

        return $handles;
    }

    /**
     * REST API discovery Link: headers.
     * * Core sends them from 'template_redirect', independently of the matching
     * &lt;head&gt; link, so removing the tag alone leaves the headers in place.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function rest_link_header(): void {
        Options::is( 'disable_rest_link_header' ) && remove_action( 'template_redirect', 'rest_output_link_header', 11 );
    }

    /**
     * Number of leading content images that skip lazy-loading.
     * * An empty or zero setting keeps the WordPress threshold, so the option can
     * never make every image lazy by accident.
     *
     * @param int $threshold Number of images loaded eagerly.
     *
     * @return int
     */
    #[Hook( 'wp_omit_loading_attr_threshold' )]
    public function eager_image_count( int $threshold ): int {
        $value = (int) Options::get( 'eager_image_count', 0 );

        return $value > 0 ? $value : $threshold;
    }

    /**
     * Mirrors the dns-prefetch host list into preconnect hints.
     * * wp_resource_hints() filters the relation types in order, so the hosts
     * resolved for dns-prefetch are known by the time preconnect is filtered.
     *
     * @param array  $urls          Resource hint URLs.
     * @param string $relation_type Resource hint relation type.
     *
     * @return array
     */
    #[Hook( 'wp_resource_hints', priority: 99, accepted_args: 2 )]
    public function preconnect_external_hosts( array $urls, string $relation_type ): array {
        if ( ! Options::is( 'preconnect_external_hosts' ) ) {
            return $urls;
        }

        if ( 'dns-prefetch' === $relation_type ) {
            $this->prefetch_hosts = [];
            $site_host            = (string) wp_parse_url( home_url(), PHP_URL_HOST );

            foreach ( $urls as $url ) {
                $host = $this->hint_host( $url );

                if ( '' !== $host && $host !== $site_host ) {
                    $this->prefetch_hosts[ $host ] = true;
                }
            }

            return $urls;
        }

        if ( 'preconnect' !== $relation_type ) {
            return $urls;
        }

        foreach ( array_keys( $this->prefetch_hosts ) as $host ) {
            $urls[] = 'https://' . $host;
        }

        return $urls;
    }

    /**
     * Saves the resized copies of uploaded images as WebP.
     * * The map is extended rather than replaced, because core relies on it to turn
     * HEIC/HEIF camera uploads into JPEG.
     *
     * @param array       $formats   Mime type mappings, source type => destination type.
     * @param string|null $filename  Path to the image.
     * @param string|null $mime_type The source image mime type.
     *
     * @return array
     */
    #[Hook( 'image_editor_output_format', accepted_args: 3 )]
    public function webp_subsizes( array $formats, ?string $filename = null, ?string $mime_type = null ): array {
        if ( ! Options::is( 'webp_subsizes' ) ) {
            return $formats;
        }

        if ( ! wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
            return $formats;
        }

        $formats['image/jpeg'] = 'image/webp';
        $formats['image/png']  = 'image/webp';

        return $formats;
    }

    /**
     * Compression quality used for generated image sizes.
     * * Values outside 1-100, including the empty field, fall back to whatever
     * WordPress picked for the format being written.
     *
     * @param int $quality Default compression quality.
     *
     * @return int
     */
    #[Hook( 'wp_editor_set_quality' )]
    public function image_quality( int $quality ): int {
        $value = (int) Options::get( 'image_quality', 0 );

        return ( $value >= 1 && $value <= 100 ) ? $value : $quality;
    }

    /**
     * The admin bar on the front end.
     * * The filter is never reached inside wp-admin, where core returns early.
     *
     * @param bool $show Whether the admin bar should be shown.
     *
     * @return bool
     */
    #[Hook( 'show_admin_bar', priority: 99 )]
    public function frontend_admin_bar( bool $show ): bool {
        return Options::is( 'frontend_admin_bar', true ) ? $show : false;
    }

    /**
     * Extracts a host name from a single wp_resource_hints() entry.
     * * Core feeds the filter bare host names, while plugins commonly add full or
     * protocol-relative URLs, and either may arrive as an attribute array.
     *
     * @param mixed $url Resource hint entry.
     *
     * @return string Host name, or an empty string when none could be resolved.
     */
    private function hint_host( mixed $url ): string {
        $href = is_array( $url ) ? ( $url['href'] ?? '' ) : $url;

        if ( ! is_string( $href ) ) {
            return '';
        }

        $href = trim( $href );

        if ( '' === $href ) {
            return '';
        }

        if ( ! str_contains( $href, '/' ) ) {
            return $href;
        }

        return (string) wp_parse_url( $href, PHP_URL_HOST );
    }

}
