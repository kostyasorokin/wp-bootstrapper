<?php
/**
 * "Upload" Files Handling
 *
 * Handles file name sanitization and other file-related tweaks.
 *
 * Based on: https://github.com/WPArtisan/wpartisan-filename-sanitizer
 *
 * @package    WP_Bootstrapper
 * @subpackage Files
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Files {

    /**
     * Disables the threshold for big image resizing.
     * When enabled, WordPress will downscale images larger than 2560px.
     *
     * @return bool|int False to disable the threshold.
     */
    #[Hook( 'big_image_size_threshold' )]
    public function big_image_size_threshold(): bool|int {
        return ! Options::is( 'big_image_size_threshold' ) ? false : 2560;
    }

    /**
     * Sanitizes the file name for uploads.
     * Converts accents to ASCII, replaces spaces/underscores with hyphens,
     * removes special characters, and ensures lowercase.
     *
     * @param string $filename The original filename.
     *
     * @return string The sanitized filename.
     */
    #[Hook( 'sanitize_file_name' )]
    public function clear_file_name( string $filename ): string {
        $sanitized_filename = remove_accents( $filename ); // Convert to ASCII

        // Standard replacements
        $invalid = [
            ' '   => '-',
            '%20' => '-',
            '_'   => '-',
        ];

        $sanitized_filename = str_replace( array_keys( $invalid ), array_values( $invalid ), $sanitized_filename );

        // Remove all non-alphanumeric except dots and hyphens
        $sanitized_filename = preg_replace( '/[^A-Za-z0-9-\.]/', '', $sanitized_filename );

        // Remove all but the last dot
        $sanitized_filename = preg_replace( '/\.(?=.*\.)/', '', $sanitized_filename );

        // Replace multiple consecutive hyphens with a single one
        $sanitized_filename = preg_replace( '/-+/', '-', $sanitized_filename );

        // Remove hyphen if it's right before the extension dot
        $sanitized_filename = str_replace( '-.', '.', $sanitized_filename );

        // Final lowercase conversion
        $sanitized_filename = strtolower( $sanitized_filename );

        /**
         * Allow further sanitization via a custom filter.
         *
         * @param string $sanitized_filename The sanitized filename.
         * @param string $filename           Original filename.
         */
        return (string) apply_filters( 'wpb_sanitize_file_name', $sanitized_filename, $filename );
    }

    /**
     * *.svg & *.svgz support in the WordPress media library.
     *
     * @param array $mimes Current allowed MIME types.
     *
     * @return array Updated MIME types.
     */
    #[Hook( 'upload_mimes' )]
    public function svg_svgz_support( array $mimes ): array {
        if ( $this->can_upload_svg() ) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }

        return $mimes;
    }

    /**
     * Validates uploaded SVG/SVGZ files before WordPress moves them into uploads.
     *
     * @param array $file Uploaded file data.
     *
     * @return array
     */
    #[Hook( 'wp_handle_upload_prefilter' )]
    public function validate_svg_upload( array $file ): array {
        $detected = wp_check_filetype_and_ext( $file['tmp_name'] ?? '', $file['name'] ?? '' );
        $ext      = strtolower( (string) ( $detected['ext'] ?? '' ) );

        if ( ! in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
            return $file;
        }

        if ( ! Options::is( 'allow_svg_uploads', false ) ) {
            $file['error'] = esc_html__( 'SVG uploads are disabled by site settings.', 'wp-bootstrapper' );

            return $file;
        }

        if ( ! $this->can_upload_svg() ) {
            $file['error'] = esc_html__( 'You are not allowed to upload SVG files.', 'wp-bootstrapper' );

            return $file;
        }

        $content = @file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $content || '' === $content ) {
            $file['error'] = esc_html__( 'Unable to read uploaded SVG file.', 'wp-bootstrapper' );

            return $file;
        }

        if ( 'svgz' === $ext ) {
            $decoded = @gzdecode( $content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( false === $decoded ) {
                $file['error'] = esc_html__( 'Invalid compressed SVG (.svgz) file.', 'wp-bootstrapper' );

                return $file;
            }

            $content = $decoded;
        }

        if ( ! $this->is_safe_svg( $content ) ) {
            $file['error'] = esc_html__( 'Unsafe SVG content detected. Upload blocked.', 'wp-bootstrapper' );
        }

        return $file;
    }

    /**
     * Checks whether current user can upload SVG files.
     */
    private function can_upload_svg(): bool {
        if ( ! is_admin() || ! Options::is( 'allow_svg_uploads', false ) ) {
            return false;
        }

        $required_capability = (string) apply_filters( 'wpb_svg_upload_capability', 'manage_options' );

        return current_user_can( $required_capability );
    }

    /**
     * Performs basic server-side SVG security checks.
     */
    private function is_safe_svg( string $content ): bool {
        $patterns = [
            '/<script[\s>]/i',
            '/<foreignobject[\s>]/i',
            '/\son[a-z0-9_-]+\s*=/i',
            '/javascript\s*:/i',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                return false;
            }
        }

        $previous = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $loaded   = $document->loadXML( $content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded || ! $document->documentElement ) {
            return false;
        }

        return 'svg' === strtolower( $document->documentElement->tagName );
    }

}
