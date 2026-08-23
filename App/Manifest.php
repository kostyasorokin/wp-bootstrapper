<?php
/**
 * Class: Manifest
 *
 * @package    KS_Bootstrapper
 * @subpackage Manifest
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a web app manifest at the site root and links it from the head.
 *
 */
class Manifest {

    private const PATH = 'manifest.json';
    private const QUERY_VAR = 'ks_manifest';
    private const RULES_VERSION = 1;
    private const RULES_OPTION = KS_BOOTSTRAPPER_OPTION_PREFIX . 'manifest_rules';
    public const DISPLAY_MODES = [ 'standalone', 'minimal-ui', 'fullscreen', 'browser' ];

    /**
     * Registers the rewrite rule for /manifest.json.
     *
     * Added at the top of the rule set: WordPress otherwise matches the address against
     * pages and attachments first, and a post with that slug is perfectly possible.
     *
     * @return void
     */
    #[Hook( 'init' )]
    public function rewrite(): void {
        if ( ! $this->enabled() ) {
            return;
        }

        add_rewrite_rule(
            '^' . preg_quote( self::PATH, '/' ) . '$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * Allows the query variable through to WP_Query.
     *
     * @param array $vars Query variables WordPress is willing to parse.
     *
     * @return array
     */
    #[Hook( 'query_vars' )]
    public function query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * Writes the rule into the stored set when the setting has just changed.
     *
     * Priority 999: after every registrar on 'init', or the flush writes a set that is
     * missing whatever registered later.
     *
     * @return void
     */
    #[Hook( 'init', priority: 999 )]
    public function flush_when_needed(): void {
        $signature = self::RULES_VERSION . ':' . ( $this->enabled() ? '1' : '0' );

        if ( get_option( self::RULES_OPTION ) === $signature ) {
            return;
        }

        flush_rewrite_rules();

        update_option( self::RULES_OPTION, $signature, true );
    }

    /**
     * Keeps the canonical redirect off the manifest address.
     *
     * The permalink structure ends in a slash, and redirect_canonical applies that to
     * anything it does not recognise as a file — including an address answered by a rewrite
     * rule rather than by a file on disk. So /manifest.json answered with a 301 to
     * /manifest.json/, which browsers follow but validators report, and which is simply the
     * wrong shape for an address that names a file.
     *
     * @param string|false $redirect The address core wants to send the visitor to.
     *
     * @return string|false
     */
    #[Hook( 'redirect_canonical', accepted_args: 2 )]
    public function no_canonical_redirect( string|false $redirect ): string|false {
        return get_query_var( self::QUERY_VAR ) ? false : $redirect;
    }

    /**
     * Serves the manifest and ends the request.
     *
     * The media type is application/manifest+json rather than application/json: browsers
     * accept either, several validators only the former.
     *
     * @return void
     */
    #[Hook( 'template_redirect' )]
    public function render(): void {
        if ( ! $this->enabled() || ! get_query_var( self::QUERY_VAR ) ) {
            return;
        }

        // 200 by hand: WP_Query found no post, because there is none to find.
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: application/manifest+json; charset=utf-8' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, encoded above.
        echo wp_json_encode( $this->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

        exit;
    }

    /**
     * Links the manifest from the head.
     *
     * Built from home_url() rather than from the current address: there is one manifest per
     * site, and a relative link from a page in a subdirectory would point at nothing.
     *
     * @return void
     */
    #[Hook( 'wp_head', priority: 2 )]
    public function link(): void {
        if ( ! $this->enabled() ) {
            return;
        }

        printf(
            '<link rel="manifest" href="%s">%s',
            esc_url( home_url( '/' . self::PATH ) ),
            PHP_EOL
        );
    }

    /**
     * The manifest document.
     *
     * @return array
     */
    public function data(): array {
        $manifest = [
            'name'       => (string) get_bloginfo( 'name' ),
            'short_name' => $this->short_name(),
            // id apart from start_url: without it the app's identity is tied to its start
            // address, and changing that address creates a second app instead of updating
            // the one people already installed.
            'id'         => '/',
            'start_url'  => '/',
            'scope'      => '/',
            'display'    => $this->display(),
            'lang'       => (string) get_bloginfo( 'language' ),
            'dir'        => is_rtl() ? 'rtl' : 'ltr',
            'icons'      => $this->icons(),
        ];

        // Optional members are added only when they carry something. An empty string is not
        // "no description" but "the description is empty", and stores and validators show it
        // exactly that way.
        $description = trim( (string) get_bloginfo( 'description' ) );

        if ( '' !== $description ) {
            $manifest['description'] = $description;
        }

        foreach ( [ 'theme_color' => 'manifest_theme_color', 'background_color' => 'manifest_background_color' ] as $member => $key ) {
            $colour = sanitize_hex_color( (string) Options::get( $key, '' ) );

            if ( is_string( $colour ) && '' !== $colour ) {
                $manifest[ $member ] = $colour;
            }
        }

        /**
         * Filters the manifest before it is encoded.
         *
         * @param array $manifest The document as this class assembled it.
         */
        return (array) apply_filters( 'ks_bootstrapper_manifest', $manifest );
    }

    /**
     * The icons, which are the WordPress Site Icon.
     *
     * @return array
     */
    private function icons(): array {
        $iconId = (int) get_option( 'site_icon' );

        if ( $iconId <= 0 ) {
            return [];
        }

        $icons = [];
        $seen  = [];

        foreach ( [ 192, 512 ] as $size ) {
            $image = wp_get_attachment_image_src( $iconId, [ $size, $size ] );

            if ( ! is_array( $image ) || '' === (string) $image[0] ) {
                continue;
            }

            [ $url, $width, $height ] = $image;

            if ( isset( $seen[ $url ] ) ) {
                continue;
            }

            $seen[ $url ] = true;

            $icons[] = [
                'src'     => $url,
                'sizes'   => (int) $width . 'x' . (int) $height,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }

        if ( [] === $icons ) {
            return [];
        }

        $icons[] = [ ...$icons[ array_key_last( $icons ) ], 'purpose' => 'maskable' ];

        return $icons;
    }

    /**
     * The short name, which falls back to the site title.
     *
     * Home screens give a name about twelve characters before they truncate it, so a site
     * whose title is longer wants somewhere to say what to shorten it to.
     *
     * @return string
     */
    private function short_name(): string {
        $short = trim( (string) Options::get( 'manifest_short_name', '' ) );

        return '' !== $short ? $short : (string) get_bloginfo( 'name' );
    }

    /**
     * The display mode, refused unless it is one the specification defines.
     *
     * @return string
     */
    private function display(): string {
        $display = (string) Options::get( 'manifest_display', 'standalone' );

        return in_array( $display, self::DISPLAY_MODES, true ) ? $display : 'standalone';
    }

    /**
     * @return bool
     */
    private function enabled(): bool {
        return Options::is( 'web_app_manifest' );
    }
}
