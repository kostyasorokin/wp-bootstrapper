<?php
/**
 * "Upload" Files Handling
 *
 * Handles file name sanitization and other file-related tweaks.
 *
 * Based on: https://github.com/WPArtisan/wpartisan-filename-sanitizer
 *
 * @package    KS_Bootstrapper
 * @subpackage Files
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Files {

    /**
     * The SVG namespace. Elements are matched by namespace and local name,
     * so a custom prefix cannot smuggle anything past the allowlist.
     */
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /**
     * The XLink namespace, used by the xlink:href references SVG 1.1 relies on.
     */
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    /**
     * The XML namespace, used by xml:space, xml:lang and friends.
     */
    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Element local names an uploaded SVG is allowed to keep.
     *
     * Everything else is removed, including <script>, <foreignObject>,
     * <handler> and the SMIL animation elements, which can assign an event
     * handler at run time.
     */
    private const ALLOWED_ELEMENTS = [
        'a', 'circle', 'clippath', 'defs', 'desc', 'ellipse', 'filter', 'g', 'image', 'line',
        'lineargradient', 'marker', 'mask', 'metadata', 'path', 'pattern', 'polygon', 'polyline',
        'radialgradient', 'rect', 'stop', 'style', 'svg', 'switch', 'symbol', 'text', 'textpath',
        'title', 'tspan', 'use', 'view',
        'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feconvolvematrix',
        'fediffuselighting', 'fedisplacementmap', 'fedistantlight', 'fedropshadow', 'feflood',
        'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur', 'feimage', 'femerge',
        'femergenode', 'femorphology', 'feoffset', 'fepointlight', 'fespecularlighting',
        'fespotlight', 'fetile', 'feturbulence',
    ];

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

        // Nothing survives the stripping above when the name is not written in Latin script
        $sanitized_filename = $this->rebuild_empty_file_name( $sanitized_filename, $filename );

        /**
         * Allow further sanitization via a custom filter.
         *
         * @param string $sanitized_filename The sanitized filename.
         * @param string $filename           Original filename.
         */
        return (string) apply_filters( 'ks_bootstrapper_sanitize_file_name', $sanitized_filename, $filename );
    }

    /**
     * Restores a base name that did not survive the ASCII stripping.
     *
     * remove_accents() transliterates Latin diacritics only, so a Cyrillic,
     * Greek, Arabic or CJK name is reduced to its extension alone. WordPress
     * would then store every such upload as "unnamed-file.ext" and each one
     * after the first would collide with it.
     *
     * @param string $sanitized_filename The stripped filename.
     * @param string $filename           The original filename.
     *
     * @return string The filename with a usable base name.
     */
    private function rebuild_empty_file_name( string $sanitized_filename, string $filename ): string {
        $extension = strtolower( (string) pathinfo( $sanitized_filename, PATHINFO_EXTENSION ) );
        $base      = trim( (string) pathinfo( $sanitized_filename, PATHINFO_FILENAME ), '-' );

        if ( '' === $base ) {
            $extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
            $base      = $this->transliterate_base_name( (string) pathinfo( $filename, PATHINFO_FILENAME ) );
        }

        return '' !== $extension ? $base . '.' . $extension : $base;
    }

    /**
     * Builds an ASCII base name for a file named in a non-Latin script.
     *
     * sanitize_title() picks up whichever transliteration a site has installed
     * (Cyr-To-Lat and friends). With none installed it percent-encodes the
     * UTF-8 bytes, which reads no better than an empty name, so a short digest
     * of the original name is used instead. The digest keeps this function
     * repeatable: WordPress sanitizes a file name more than once per upload.
     *
     * @param string $base The original base name.
     *
     * @return string An ASCII base name, never empty.
     */
    private function transliterate_base_name( string $base ): string {
        $transliterated = sanitize_title( $base );

        // Percent escapes mean nothing was transliterated, only encoded.
        if ( str_contains( $transliterated, '%' ) ) {
            $transliterated = '';
        }

        $transliterated = trim( (string) preg_replace( '/[^a-z0-9-]/', '', strtolower( $transliterated ) ), '-' );

        return '' !== $transliterated ? $transliterated : 'file-' . substr( md5( $base ), 0, 8 );
    }

    /**
     * *.svg support in the WordPress media library.
     *
     * Compressed *.svgz files are deliberately not offered: WordPress compares
     * the real MIME type of the uploaded bytes (application/gzip) with the one
     * the extension maps to and refuses the file, and a web server would serve
     * the stored gzip without the Content-Encoding header a browser needs.
     *
     * @param array $mimes Current allowed MIME types.
     *
     * @return array Updated MIME types.
     */
    #[Hook( 'upload_mimes' )]
    public function svg_svgz_support( array $mimes ): array {
        if ( $this->can_upload_svg() ) {
            $mimes['svg'] = 'image/svg+xml';
        }

        return $mimes;
    }

    /**
     * Validates uploaded SVG files before WordPress moves them into uploads.
     *
     * Registered for sideloads as well, because WP-CLI imports and migration
     * tools write into the same uploads directory through that path.
     *
     * @param array $file Uploaded file data.
     *
     * @return array
     */
    #[Hook( 'wp_handle_upload_prefilter' )]
    #[Hook( 'wp_handle_sideload_prefilter' )]
    public function validate_svg_upload( array $file ): array {
        // Read the extension from the name: core blanks its own answer for anything it dislikes.
        $ext = strtolower( (string) pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) );

        if ( 'svg' !== $ext ) {
            return $file;
        }

        if ( ! Options::is( 'allow_svg_uploads', false ) ) {
            $file['error'] = esc_html__( 'SVG uploads are disabled by site settings.', 'ks-bootstrapper' );

            return $file;
        }

        if ( ! $this->can_upload_svg() ) {
            $file['error'] = esc_html__( 'You are not allowed to upload SVG files.', 'ks-bootstrapper' );

            return $file;
        }

        $content = @file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $content || '' === $content ) {
            $file['error'] = esc_html__( 'Unable to read uploaded SVG file.', 'ks-bootstrapper' );

            return $file;
        }

        $sanitized = $this->sanitize_svg( $content );

        if ( null === $sanitized ) {
            $file['error'] = esc_html__( 'This file could not be read as safe SVG markup. Upload blocked.', 'ks-bootstrapper' );

            return $file;
        }

        if ( $sanitized !== $content ) {
            if ( false === @file_put_contents( (string) $file['tmp_name'], $sanitized ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                $file['error'] = esc_html__( 'Unable to store the sanitized SVG file.', 'ks-bootstrapper' );

                return $file;
            }

            $file['size'] = strlen( $sanitized );
        }

        return $file;
    }

    /**
     * Checks whether current user can upload SVG files.
     *
     * No screen check here: the block editor uploads over the REST API, where
     * is_admin() is false. The capability is the access control.
     */
    private function can_upload_svg(): bool {
        if ( ! Options::is( 'allow_svg_uploads', false ) ) {
            return false;
        }

        $required_capability = (string) apply_filters( 'ks_bootstrapper_svg_upload_capability', 'manage_options' );

        return current_user_can( $required_capability );
    }

    /**
     * Reduces SVG markup to the elements and attributes on the allowlist.
     *
     * @param string $content The uploaded file contents.
     *
     * @return string|null The cleaned markup, or null when the file cannot be
     *                     treated as SVG at all.
     */
    private function sanitize_svg( string $content ): ?string {
        $document = $this->parse_svg( $content );

        if ( ! $document instanceof \DOMDocument ) {
            return null;
        }

        $xpath = new \DOMXPath( $document );

        // An xml-stylesheet processing instruction can pull in a remote transform.
        foreach ( iterator_to_array( $xpath->query( '//processing-instruction()' ) ) as $instruction ) {
            $instruction->parentNode?->removeChild( $instruction );
        }

        foreach ( iterator_to_array( $xpath->query( '//*' ) ) as $element ) {
            if ( ! $element instanceof \DOMElement ) {
                continue;
            }

            if ( ! $this->is_allowed_element( $element ) ) {
                $element->parentNode?->removeChild( $element );

                continue;
            }

            $this->strip_attributes( $element );
        }

        $sanitized = $document->saveXML();

        return is_string( $sanitized ) && '' !== $sanitized ? $sanitized : null;
    }

    /**
     * Parses SVG markup, refusing anything that is not a plain SVG document.
     *
     * @param string $content The uploaded file contents.
     *
     * @return \DOMDocument|null The parsed document, or null when the file is
     *                           malformed, carries entity definitions, or is
     *                           rooted in something other than <svg>.
     */
    private function parse_svg( string $content ): ?\DOMDocument {
        $previous = libxml_use_internal_errors( true );
        $document = new \DOMDocument();
        $loaded   = $document->loadXML( $content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded || ! $document->documentElement instanceof \DOMElement ) {
            return null;
        }

        // A doctype is fine, an internal subset is not: it can define entities.
        if ( $document->doctype
            && ( 'svg' !== strtolower( (string) $document->doctype->name ) || null !== $document->doctype->internalSubset ) ) {
            return null;
        }

        $root = $document->documentElement;

        if ( 'svg' !== strtolower( (string) $root->localName ) || ! $this->is_svg_namespace( $root->namespaceURI ) ) {
            return null;
        }

        return $document;
    }

    /**
     * Removes every attribute of an element that is not allowed to stay.
     *
     * @param \DOMElement $element The element to clean.
     *
     * @return void
     */
    private function strip_attributes( \DOMElement $element ): void {
        foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
            if ( $attribute instanceof \DOMAttr && ! $this->is_allowed_attribute( $attribute ) ) {
                $element->removeAttributeNode( $attribute );
            }
        }
    }

    /**
     * Checks an element against the allowlist.
     *
     * @param \DOMElement $element The element to check.
     *
     * @return bool True when the element may stay.
     */
    private function is_allowed_element( \DOMElement $element ): bool {
        if ( ! $this->is_svg_namespace( $element->namespaceURI ) ) {
            return false;
        }

        $name = strtolower( (string) $element->localName );

        if ( ! in_array( $name, self::ALLOWED_ELEMENTS, true ) ) {
            return false;
        }

        // Stylesheets may style this document, not fetch another one.
        return 'style' !== $name || $this->is_allowed_value( $element->textContent );
    }

    /**
     * Checks an attribute against the allowlist rules.
     *
     * @param \DOMAttr $attribute The attribute to check.
     *
     * @return bool True when the attribute may stay.
     */
    private function is_allowed_attribute( \DOMAttr $attribute ): bool {
        $namespace = $attribute->namespaceURI;

        if ( null !== $namespace
            && self::SVG_NAMESPACE !== $namespace
            && self::XLINK_NAMESPACE !== $namespace
            && self::XML_NAMESPACE !== $namespace ) {
            return false;
        }

        $name = strtolower( (string) $attribute->localName );

        // Every event handler is an on* attribute.
        if ( str_starts_with( $name, 'on' ) ) {
            return false;
        }

        if ( 'href' === $name || 'src' === $name ) {
            return $this->is_allowed_reference( $attribute->value );
        }

        return $this->is_allowed_value( $attribute->value );
    }

    /**
     * Checks a reference used by href, xlink:href or src.
     *
     * @param string $value The attribute value.
     *
     * @return bool True when the reference stays inside this site.
     */
    private function is_allowed_reference( string $value ): bool {
        $reference = $this->normalize_value( $value );

        if ( '' === $reference || str_starts_with( $reference, '#' ) ) {
            return true;
        }

        // An inline raster image is inert; an inline SVG or HTML document is not.
        if ( preg_match( '#^data:image/(png|jpe?g|gif|webp);base64,#', $reference ) ) {
            return true;
        }

        // Protocol-relative URLs point off site.
        if ( str_starts_with( $reference, '//' ) ) {
            return false;
        }

        if ( preg_match( '#^[a-z][a-z0-9+.-]*:#', $reference ) ) {
            return str_starts_with( $reference, strtolower( home_url( '/' ) ) );
        }

        return true; // A relative path resolves against this site.
    }

    /**
     * Checks a style declaration or a plain attribute value.
     *
     * @param string $value The value to check.
     *
     * @return bool True when the value neither scripts nor fetches anything.
     */
    private function is_allowed_value( string $value ): bool {
        $normalized = $this->normalize_value( $value );

        foreach ( [ 'javascript:', '@import', 'expression(' ] as $needle ) {
            if ( str_contains( $normalized, $needle ) ) {
                return false;
            }
        }

        // CSS may only reference elements of this very document: url(#id).
        return ! preg_match( '/url\([\'"]?(?!#)/', $normalized );
    }

    /**
     * Flattens a value so that encoded and padded payloads compare equal.
     *
     * @param string $value The raw value.
     *
     * @return string The decoded, whitespace-free, lowercase value.
     */
    private function normalize_value( string $value ): string {
        $decoded = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5 );

        return strtolower( (string) preg_replace( '/[\x00-\x20]+/', '', $decoded ) );
    }

    /**
     * Checks whether a namespace belongs to SVG.
     *
     * @param string|null $namespace The namespace URI, null when undeclared.
     *
     * @return bool
     */
    private function is_svg_namespace( ?string $namespace ): bool {
        return null === $namespace || self::SVG_NAMESPACE === $namespace;
    }

}
