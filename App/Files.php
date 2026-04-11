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
     * When enabled, WordPress will not downscale images larger than 2560px.
     *
     * @return bool|int False to disable the threshold.
     */
    #[Hook( 'big_image_size_threshold' )]
    public function disableBigImageThreshold(): bool|int {
        return Options::is( 'disable_big_image_threshold' ) ? false : 2560;
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
    public function clearFileName( string $filename ): string {
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
        if ( is_admin() ) {
            $mimes['svg']  = 'image/svg+xml';
            $mimes['svgz'] = 'image/svg+xml';
        }

        return $mimes;
    }

}