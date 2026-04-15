<?php
/**
 * Security hardening helpers.
 *
 * @package    WP_Bootstrapper
 * @subpackage Security
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB;

use DOMDocument;
use DOMElement;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Security {

    /**
     * Cron event name for uploads hardening.
     */
    private const string CRON_HOOK = 'wpb_security_daily';

    /**
     * Marker used for the managed .htaccess block.
     */
    private const string HTACCESS_MARKER = 'WP Bootstrapper Uploads Protection';

    /**
     * Marker used for the managed web.config rule.
     */
    private const string WEB_CONFIG_RULE = 'WPBootstrapperUploadsProtection';

    /**
     * Transient key for once-per-day self-heal checks.
     */
    private const string SELF_HEAL_TRANSIENT = 'wpb_security_self_heal_due';

    /**
     * Maximum number of files to inspect in a single cleanup pass.
     */
    private const int MAX_FILES_PER_RUN = 5000;

    /**
     * Maximum runtime for a single cleanup pass in seconds.
     */
    private const int MAX_RUNTIME_SECONDS = 10;

    /**
     * File extensions that should never live in uploads.
     *
     * @var string[]
     */
    private const array BLOCKED_EXTENSIONS = [
        'php',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'phtml',
        'phar',
    ];

    /**
     * Ensures cron is scheduled and uploads are protected.
     */
    #[Hook( 'init', priority: 1 )]
    public function bootstrap(): void {
        self::ensure_cron();
        self::maybe_self_heal();
    }

    /**
     * Blocks guest author enumeration requests by forcing a 404.
     */
    #[Hook( 'template_redirect', priority: 0 )]
    public function block_author_enumeration(): void {
        if ( ! $this->should_block_author_enumeration() ) {
            return;
        }

        global $wp_query;

        if ( isset( $wp_query ) ) {
            $wp_query->set_404();
        }

        status_header( 404 );
        nocache_headers();

        if ( function_exists( 'redirect_guess_404_permalink' ) ) {
            remove_filter( 'template_redirect', 'redirect_guess_404_permalink' );
        }
    }

    /**
     * Prevents canonical redirects from resolving author enumeration attempts.
     *
     * @param string|false $redirect Redirect URL.
     * @param string       $request  Requested URL.
     *
     * @return string|false
     */
    #[Hook( 'redirect_canonical', accepted_args: 2 )]
    public function disable_author_enumeration_canonical( string|false $redirect, string $request ): string|false {
        if ( ! $this->should_block_author_enumeration() ) {
            return $redirect;
        }

        return false;
    }

    /**
     * Daily hardening pass for uploads.
     */
    #[Hook( self::CRON_HOOK )]
    public function daily_maintenance(): void {
        self::protect_uploads_directory();
        self::delete_php_files_from_uploads();
    }

    /**
     * Activation tasks.
     */
    public static function activate(): void {
        self::ensure_cron();
        self::protect_uploads_directory();
        self::delete_php_files_from_uploads();
    }

    /**
     * Deactivation tasks.
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Schedules the daily hardening event if it does not exist yet.
     */
    private static function ensure_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Determines whether the current request is an author enumeration attempt.
     */
    private function should_block_author_enumeration(): bool {
        if ( is_admin() || is_user_logged_in() || ! Options::is( 'block_author_enumeration', false ) ) {
            return false;
        }

        if ( is_author() ) {
            return true;
        }

        $author_id = get_query_var( 'author' );
        if ( '' !== (string) $author_id ) {
            return true;
        }

        $author_name = get_query_var( 'author_name' );

        return is_string( $author_name ) && '' !== $author_name;
    }

    /**
     * Runs infrequent self-heal checks during regular traffic.
     */
    private static function maybe_self_heal(): void {
        if ( get_transient( self::SELF_HEAL_TRANSIENT ) ) {
            return;
        }

        self::protect_uploads_directory();
        set_transient( self::SELF_HEAL_TRANSIENT, 1, DAY_IN_SECONDS );
    }

    /**
     * Writes managed server config files into uploads to block PHP execution.
     */
    private static function protect_uploads_directory(): bool {
        $uploads = wp_upload_dir();
        $base_dir = trailingslashit( (string) ( $uploads['basedir'] ?? '' ) );

        if (
            ! empty( $uploads['error'] ) ||
            '' === $base_dir ||
            ! is_dir( $base_dir ) ||
            ! wp_is_writable( $base_dir )
        ) {
            return false;
        }

        $protected = false;

        $htaccess_file = $base_dir . '.htaccess';
        $marker_start  = '# BEGIN ' . self::HTACCESS_MARKER;
        $marker_end    = '# END ' . self::HTACCESS_MARKER;
        $block         = implode(
            PHP_EOL,
            [
                $marker_start,
                'Options -Indexes',
                '<FilesMatch "\.(php|php3|php4|php5|php7|php8|phtml|phar)$">',
                '    <IfModule mod_authz_core.c>',
                '        Require all denied',
                '    </IfModule>',
                '    <IfModule !mod_authz_core.c>',
                '        Order Allow,Deny',
                '        Deny from all',
                '    </IfModule>',
                '</FilesMatch>',
                $marker_end,
                '',
            ]
        );

        $contents = file_exists( $htaccess_file )
            ? (string) file_get_contents( $htaccess_file )
            : '';

        $pattern = '/'
            . preg_quote( $marker_start, '/' )
            . '.*?'
            . preg_quote( $marker_end, '/' )
            . '\R*/s';

        if ( preg_match( $pattern, $contents ) ) {
            $updated_contents = (string) preg_replace( $pattern, $block, $contents );
        } else {
            $updated_contents = rtrim( $contents ) . ( '' !== trim( $contents ) ? PHP_EOL . PHP_EOL : '' ) . $block;
        }

        if ( $updated_contents === $contents || self::atomic_write( $htaccess_file, $updated_contents ) ) {
            $protected = true;
        }

        $web_config_file = $base_dir . 'web.config';
        if ( self::protect_uploads_web_config( $web_config_file ) ) {
            $protected = true;
        }

        return $protected;
    }

    /**
     * Writes a managed IIS web.config file into uploads when possible.
     */
    private static function protect_uploads_web_config( string $web_config_file ): bool {
        $document = new DOMDocument( '1.0', 'UTF-8' );
        $document->formatOutput = true;

        if ( file_exists( $web_config_file ) ) {
            $contents = (string) file_get_contents( $web_config_file );

            if ( '' !== trim( $contents ) ) {
                libxml_use_internal_errors( true );
                $loaded = $document->loadXML( $contents );
                libxml_clear_errors();
                libxml_use_internal_errors( false );

                if ( ! $loaded ) {
                    self::debug_log( 'Unable to parse uploads/web.config, skipping IIS protection update.' );

                    return false;
                }
            }
        }

        if ( ! $document->documentElement ) {
            $configuration = $document->createElement( 'configuration' );
            $document->appendChild( $configuration );
        }

        $configuration = $document->documentElement;
        if ( ! $configuration instanceof DOMElement ) {
            return false;
        }

        $system_web_server = self::get_or_create_child( $document, $configuration, 'system.webServer' );
        $security          = self::get_or_create_child( $document, $system_web_server, 'security' );
        $request_filtering = self::get_or_create_child( $document, $security, 'requestFiltering' );
        $file_extensions   = self::get_or_create_child( $document, $request_filtering, 'fileExtensions' );
        $handlers          = self::get_or_create_child( $document, $system_web_server, 'handlers' );
        $directory_browse  = self::get_or_create_child( $document, $system_web_server, 'directoryBrowse' );

        $directory_browse->setAttribute( 'enabled', 'false' );

        foreach ( self::BLOCKED_EXTENSIONS as $extension ) {
            $normalized_extension = '.' . $extension;
            if ( ! self::has_matching_element( $file_extensions, 'add', 'fileExtension', $normalized_extension ) ) {
                $add = $document->createElement( 'add' );
                $add->setAttribute( 'fileExtension', $normalized_extension );
                $add->setAttribute( 'allowed', 'false' );
                $file_extensions->appendChild( $add );
            }
        }

        if ( ! self::has_matching_element( $handlers, 'add', 'name', self::WEB_CONFIG_RULE ) ) {
            $handler = $document->createElement( 'add' );
            $handler->setAttribute( 'name', self::WEB_CONFIG_RULE );
            $handler->setAttribute( 'path', '*.php' );
            $handler->setAttribute( 'verb', '*' );
            $handler->setAttribute( 'modules', 'StaticFileModule' );
            $handler->setAttribute( 'resourceType', 'Either' );
            $handler->setAttribute( 'requireAccess', 'Read' );
            $handlers->appendChild( $handler );
        }

        return self::atomic_write( $web_config_file, $document->saveXML() ?: '' );
    }

    /**
     * Gets an existing XML child or creates it if missing.
     */
    private static function get_or_create_child( DOMDocument $document, DOMElement $parent, string $tag_name ): DOMElement {
        foreach ( $parent->childNodes as $child ) {
            if ( $child instanceof DOMElement && $tag_name === $child->tagName ) {
                return $child;
            }
        }

        $child = $document->createElement( $tag_name );
        $parent->appendChild( $child );

        return $child;
    }

    /**
     * Checks whether a matching XML element already exists.
     */
    private static function has_matching_element(
        DOMElement $parent,
        string $tag_name,
        string $attribute_name,
        string $attribute_value
    ): bool {
        foreach ( $parent->childNodes as $child ) {
            if (
                $child instanceof DOMElement &&
                $tag_name === $child->tagName &&
                $attribute_value === $child->getAttribute( $attribute_name )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes files atomically to reduce the risk of partial config writes.
     */
    private static function atomic_write( string $path, string $contents ): bool {
        $directory = dirname( $path );
        $temporary = tempnam( $directory, 'wpb_' );

        if ( false === $temporary ) {
            return false;
        }

        if ( false === file_put_contents( $temporary, $contents ) ) {
            wp_delete_file( $temporary );

            return false;
        }

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        WP_Filesystem();
        global $wp_filesystem;

        if ( ! is_object( $wp_filesystem ) || ! $wp_filesystem->move( $temporary, $path, true ) ) {
            wp_delete_file( $temporary );

            return false;
        }

        return true;
    }

    /**
     * Writes debug messages only when WP_DEBUG is enabled.
     */
    private static function debug_log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Bootstrapper Security: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Recursively removes PHP-like files from uploads.
     */
    private static function delete_php_files_from_uploads(): int {
        $uploads = wp_upload_dir();
        $base_dir = (string) ( $uploads['basedir'] ?? '' );

        if ( ! empty( $uploads['error'] ) || '' === $base_dir || ! is_dir( $base_dir ) ) {
            return 0;
        }

        $deleted  = 0;
        $checked  = 0;
        $started  = microtime( true );
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        /** @var SplFileInfo $file */
        foreach ( $iterator as $file ) {
            if ( ++$checked > self::MAX_FILES_PER_RUN ) {
                self::debug_log( 'Uploads cleanup stopped after reaching file scan limit.' );
                break;
            }

            if ( ( microtime( true ) - $started ) >= self::MAX_RUNTIME_SECONDS ) {
                self::debug_log( 'Uploads cleanup stopped after reaching runtime limit.' );
                break;
            }

            if ( ! $file->isFile() ) {
                continue;
            }

            $extension = strtolower( $file->getExtension() );
            if ( ! in_array( $extension, self::BLOCKED_EXTENSIONS, true ) ) {
                continue;
            }

            $pathname = $file->getPathname();

            if ( wp_is_writable( $pathname ) && wp_delete_file( $pathname ) ) {
                ++$deleted;
                self::debug_log( 'Removed blocked file from uploads: ' . $pathname );
            }
        }

        return $deleted;
    }

}
