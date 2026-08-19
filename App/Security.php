<?php
/**
 * Security hardening helpers.
 *
 * @package    KS_Bootstrapper
 * @subpackage Security
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper;

use DOMDocument;
use DOMElement;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use WP_Error;
use WP_REST_Request;
use KonstantinSorokin\Bootstrapper\Attributes\Hook;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Security {

    /**
     * Cron event name for uploads hardening.
     */
    private const string CRON_HOOK = 'ks_bootstrapper_security_daily';

    /**
     * Marker used for the managed .htaccess block.
     */
    private const string HTACCESS_MARKER = 'KS Bootstrapper Uploads Protection';

    /**
     * Marker used for the managed web.config rule.
     */
    private const string WEB_CONFIG_RULE = 'KSBootstrapperUploadsProtection';

    /**
     * Transient key for once-per-day self-heal checks.
     */
    private const string SELF_HEAL_TRANSIENT = 'ks_bootstrapper_security_self_heal_due';

    /**
     * Transient key holding the report of the last uploads purge.
     */
    private const string PURGE_REPORT_TRANSIENT = 'ks_bootstrapper_uploads_purge_report';

    /**
     * Query argument used to dismiss the uploads purge notice.
     */
    private const string PURGE_DISMISS_ARG = 'ks_bootstrapper_dismiss_purge_report';

    /**
     * Maximum number of files to inspect in a single cleanup pass.
     */
    private const int MAX_FILES_PER_RUN = 5000;

    /**
     * Maximum runtime for a single cleanup pass in seconds.
     */
    private const int MAX_RUNTIME_SECONDS = 10;

    /**
     * Largest size, in bytes, a file may have and still be treated as a directory guard.
     */
    private const int GUARD_FILE_MAX_SIZE = 256;

    /**
     * Failed login attempts tolerated from one address before the lockout starts.
     */
    private const int LOGIN_MAX_ATTEMPTS = 5;

    /**
     * Lifetime of the failed login counter, which is also the maximum lockout length.
     */
    private const int LOGIN_LOCKOUT_SECONDS = 15 * MINUTE_IN_SECONDS;

    /**
     * Error code returned by the login throttle.
     */
    private const string LOGIN_ERROR_CODE = 'ks_bootstrapper_login_throttled';

    /**
     * Prefix for the per-address failed login transients.
     */
    private const string LOGIN_TRANSIENT_PREFIX = 'ks_bootstrapper_login_fail_';

    /**
     * Path fragment identifying the wordpress.org browser check endpoint.
     */
    private const string BROWSE_HAPPY_PATH = '/core/browse-happy/';

    /**
     * Referrer policy sent with front-end responses.
     */
    private const string REFERRER_POLICY = 'strict-origin-when-cross-origin';

    /**
     * Browser features denied to this document and everything it embeds.
     */
    private const string PERMISSIONS_POLICY = 'geolocation=(), camera=(), microphone=(), payment=(), usb=(), browsing-topics=()';

    /**
     * Lifetime advertised by the Strict-Transport-Security header, in seconds.
     */
    private const int HSTS_MAX_AGE = 180 * DAY_IN_SECONDS;

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
     * Metadata fields cleared from the attachment record when camera data is stripped.
     *
     * @var string[]
     */
    private const array STRIPPED_META_FIELDS = [
        'credit',
        'camera',
        'caption',
        'copyright',
        'title',
        'keywords',
        'created_timestamp',
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

            /*
             * set_404() restores is_feed on purpose and never touches is_embed or the
             * result set, so the template loader would still serve /author/<slug>/feed/
             * and /author/<slug>/embed/ with the author's posts behind a 404 status line.
             */
            $wp_query->is_feed         = false;
            $wp_query->is_comment_feed = false;
            $wp_query->is_embed        = false;
            $wp_query->posts           = [];
            $wp_query->post            = null;
            $wp_query->post_count      = 0;
            $wp_query->current_post    = -1;
            $wp_query->max_num_pages   = 0;
        }

        status_header( 404 );
        nocache_headers();
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
     * Stops core from guessing a permalink for a blocked author request.
     *
     * @param bool $guess Whether the 404 permalink guess may run.
     *
     * @return bool
     */
    #[Hook( 'do_redirect_guess_404_permalink' )]
    public function disable_redirect_guess( bool $guess ): bool {
        return $this->should_block_author_enumeration() ? false : $guess;
    }

    /**
     * Rejects guest requests to the REST users routes.
     *
     * @param mixed                $result  Pre-dispatch result, null unless already short-circuited.
     * @param mixed                $server  REST server instance.
     * @param WP_REST_Request|null $request Request being dispatched.
     *
     * @return mixed
     */
    #[Hook( 'rest_pre_dispatch', accepted_args: 3 )]
    public function restrict_rest_users( mixed $result, mixed $server, ?WP_REST_Request $request = null ): mixed {
        if ( null !== $result || ! $request instanceof WP_REST_Request ) {
            return $result;
        }

        if ( ! $this->blocks_user_enumeration() ) {
            return $result;
        }

        if ( ! preg_match( '#^/wp/v2/users\b#', untrailingslashit( (string) $request->get_route() ) ) ) {
            return $result;
        }

        return new WP_Error(
            'rest_user_cannot_view',
            __( 'Sorry, you are not allowed to list users.', 'ks-bootstrapper' ),
            [ 'status' => rest_authorization_required_code() ]
        );
    }

    /**
     * Removes the core users provider from the WordPress sitemap index.
     *
     * @param mixed  $provider Sitemap provider instance.
     * @param string $name     Provider name.
     *
     * @return mixed
     */
    #[Hook( 'wp_sitemaps_add_provider', accepted_args: 2 )]
    public function remove_users_sitemap_provider( mixed $provider, string $name ): mixed {
        if ( 'users' === $name && Options::is( 'block_author_enumeration' ) ) {
            return false;
        }

        return $provider;
    }

    /**
     * Empties the author list an SEO plugin would publish in its sitemap.
     *
     * @param array $users Users the sitemap would list.
     *
     * @return array
     */
    #[Hook( 'wpseo_sitemap_exclude_author' )]
    public function exclude_authors_from_sitemap( array $users ): array {
        return Options::is( 'block_author_enumeration' ) ? [] : $users;
    }

    /**
     * Disables the XML-RPC methods that require authentication.
     *
     * @param bool $enabled Whether authenticated XML-RPC is enabled.
     *
     * @return bool
     */
    #[Hook( 'xmlrpc_enabled' )]
    public function disable_xmlrpc( bool $enabled ): bool {
        return Options::is( 'disable_xmlrpc' ) ? false : $enabled;
    }

    /**
     * Empties the XML-RPC method table, including the unauthenticated pingback methods.
     *
     * @param array $methods Registered XML-RPC methods.
     *
     * @return array
     */
    #[Hook( 'xmlrpc_methods' )]
    public function disable_xmlrpc_methods( array $methods ): array {
        return Options::is( 'disable_xmlrpc' ) ? [] : $methods;
    }

    /**
     * Drops the X-Pingback header that advertises the XML-RPC endpoint.
     *
     * @param array $headers Response headers.
     *
     * @return array
     */
    #[Hook( 'wp_headers' )]
    public function remove_pingback_header( array $headers ): array {
        if ( Options::is( 'disable_xmlrpc' ) ) {
            unset( $headers['X-Pingback'] );
        }

        return $headers;
    }

    /**
     * Reports every post as closed for pings.
     *
     * @param bool $open    Whether the post accepts pings.
     * @param int  $post_id Post ID.
     *
     * @return bool
     */
    #[Hook( 'pings_open', accepted_args: 2 )]
    public function close_pings( bool $open, int $post_id ): bool {
        return Options::is( 'disable_xmlrpc' ) ? false : $open;
    }

    /**
     * Switches Application Passwords off for the whole site.
     *
     * @param bool $available Whether Application Passwords are available.
     *
     * @return bool
     */
    #[Hook( 'wp_is_application_passwords_available' )]
    public function disable_application_passwords( bool $available ): bool {
        return Options::is( 'disable_application_passwords' ) ? false : $available;
    }

    /**
     * Replaces login failures that name the account with one neutral message.
     *
     * @param WP_Error $errors      Login page errors.
     * @param string   $redirect_to Redirect destination URL.
     *
     * @return WP_Error
     */
    #[Hook( 'wp_login_errors', accepted_args: 2 )]
    public function generic_login_errors( WP_Error $errors, string $redirect_to ): WP_Error {
        if ( ! Options::is( 'generic_login_errors' ) ) {
            return $errors;
        }

        $leaky = array_intersect(
            [ 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed' ],
            $errors->get_error_codes()
        );

        if ( ! $leaky ) {
            return $errors;
        }

        foreach ( $leaky as $code ) {
            $errors->remove( $code );
        }

        $errors->add(
            'authentication_failed',
            __( '<strong>Error:</strong> Invalid username, email address or incorrect password.', 'ks-bootstrapper' )
        );

        return $errors;
    }

    /**
     * Rejects authentication attempts from an address that has failed too often.
     *
     * @param mixed  $user     Authentication result so far.
     * @param string $username Submitted username or email address.
     * @param string $password Submitted password.
     *
     * @return mixed
     */
    #[Hook( 'authenticate', priority: 30, accepted_args: 3 )]
    public function throttle_login( mixed $user, string $username, string $password ): mixed {
        if ( ! Options::is( 'limit_login_attempts' ) || '' === $username || '' === $password ) {
            return $user;
        }

        $key = self::login_throttle_key();

        if ( '' === $key || (int) get_transient( $key ) < self::LOGIN_MAX_ATTEMPTS ) {
            return $user;
        }

        return new WP_Error(
            self::LOGIN_ERROR_CODE,
            __( '<strong>Error:</strong> Too many failed login attempts. Please try again later.', 'ks-bootstrapper' )
        );
    }

    /**
     * Counts a failed login attempt against the connecting address.
     *
     * @param string   $username Submitted username or email address.
     * @param WP_Error $error    Authentication failure details.
     */
    #[Hook( 'wp_login_failed', accepted_args: 2 )]
    public function count_failed_login( string $username, WP_Error $error ): void {
        // Our own rejection must not extend the lockout, otherwise it never expires.
        if ( ! Options::is( 'limit_login_attempts' ) || self::LOGIN_ERROR_CODE === $error->get_error_code() ) {
            return;
        }

        $key = self::login_throttle_key();

        if ( '' === $key ) {
            return;
        }

        set_transient( $key, (int) get_transient( $key ) + 1, self::LOGIN_LOCKOUT_SECONDS );
    }

    /**
     * Clears the failed login counter once an address authenticates successfully.
     *
     * @param string $user_login Login name of the authenticated user.
     * @param mixed  $user       Authenticated user object.
     */
    #[Hook( 'wp_login', accepted_args: 2 )]
    public function clear_login_throttle( string $user_login, mixed $user = null ): void {
        if ( ! Options::is( 'limit_login_attempts' ) ) {
            return;
        }

        $key = self::login_throttle_key();

        if ( '' !== $key ) {
            delete_transient( $key );
        }
    }

    /**
     * Blocks the dashboard request that posts the browser user agent to wordpress.org.
     *
     * @param mixed  $preempt Short-circuit value for the HTTP request.
     * @param array  $args    Request arguments.
     * @param string $url     Request URL.
     *
     * @return mixed
     */
    #[Hook( 'pre_http_request', accepted_args: 3 )]
    public function block_browse_happy( mixed $preempt, array $args, string $url ): mixed {
        if ( ! Options::is( 'disable_browse_happy' ) || ! str_contains( $url, self::BROWSE_HAPPY_PATH ) ) {
            return $preempt;
        }

        return new WP_Error(
            'ks_bootstrapper_request_blocked',
            __( 'Browser version check blocked by site settings.', 'ks-bootstrapper' )
        );
    }

    /**
     * Removes the browser upgrade notice from the dashboard.
     */
    #[Hook( 'wp_dashboard_setup', priority: 99 )]
    public function remove_browser_nag_widget(): void {
        if ( Options::is( 'disable_browse_happy' ) ) {
            remove_meta_box( 'dashboard_browser_nag', 'dashboard', 'normal' );
        }
    }

    /**
     * Sends the hardening response headers core leaves off front-end responses.
     */
    #[Hook( 'send_headers' )]
    public function security_headers(): void {
        if ( ! Options::is( 'security_headers' ) || headers_sent() ) {
            return;
        }

        header( 'X-Content-Type-Options: nosniff' );
        header( 'Referrer-Policy: ' . self::REFERRER_POLICY );
        header( 'Permissions-Policy: ' . self::PERMISSIONS_POLICY );
    }

    /**
     * Tells browsers to reach this site over HTTPS only.
     *
     * Registered on the three surfaces core covers for its own headers, so the
     * login screen and the dashboard are protected alongside the front end.
     */
    #[Hook( 'send_headers' )]
    #[Hook( 'login_init' )]
    #[Hook( 'admin_init' )]
    public function hsts_header(): void {
        if ( ! Options::is( 'hsts' ) || ! is_ssl() || headers_sent() ) {
            return;
        }

        header( 'Strict-Transport-Security: max-age=' . self::HSTS_MAX_AGE );
    }

    /**
     * Removes camera and authorship metadata from freshly uploaded JPEG files.
     *
     * @param array  $metadata      Generated attachment metadata.
     * @param int    $attachment_id Attachment ID.
     * @param string $context       Either 'create' or 'update'.
     *
     * @return array
     */
    #[Hook( 'wp_generate_attachment_metadata', priority: 99, accepted_args: 3 )]
    public function strip_upload_metadata( array $metadata, int $attachment_id, string $context ): array {
        if ( ! Options::is( 'strip_upload_metadata' ) || 'create' !== $context ) {
            return $metadata;
        }

        if ( 'image/jpeg' !== get_post_mime_type( $attachment_id ) ) {
            return $metadata;
        }

        $file = (string) get_attached_file( $attachment_id );

        if ( '' === $file || ! file_exists( $file ) ) {
            return $metadata;
        }

        if ( self::strip_jpeg_app_segments( $file ) && isset( $metadata['filesize'] ) ) {
            $metadata['filesize'] = wp_filesize( $file );
        }

        // The untouched original kept beside a scaled upload carries the same data.
        if ( ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
            self::strip_jpeg_app_segments( path_join( dirname( $file ), $metadata['original_image'] ) );
        }

        if ( isset( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] ) ) {
            foreach ( self::STRIPPED_META_FIELDS as $field ) {
                $metadata['image_meta'][ $field ] = is_array( $metadata['image_meta'][ $field ] ?? null ) ? [] : '';
            }
        }

        return $metadata;
    }

    /**
     * Shows the report of the last uploads purge to site administrators.
     */
    #[Hook( 'admin_notices' )]
    public function uploads_purge_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $report = get_transient( self::PURGE_REPORT_TRANSIENT );

        if ( ! is_array( $report ) || ! $report ) {
            return;
        }

        $dismiss_url = wp_nonce_url(
            add_query_arg( self::PURGE_DISMISS_ARG, 1 ),
            self::PURGE_DISMISS_ARG
        );

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'Bootstrapper removed executable files from the uploads directory:', 'ks-bootstrapper' );
        echo '</p><ul style="margin-left:2em;list-style:disc;">';

        foreach ( $report as $path ) {
            echo '<li><code>' . esc_html( (string) $path ) . '</code></li>';
        }

        echo '</ul><p><a href="' . esc_url( $dismiss_url ) . '">';
        echo esc_html__( 'Dismiss this report', 'ks-bootstrapper' );
        echo '</a></p></div>';
    }

    /**
     * Clears the uploads purge report when the administrator dismisses it.
     */
    #[Hook( 'admin_init' )]
    public function dismiss_uploads_purge_notice(): void {
        if ( empty( $_GET[ self::PURGE_DISMISS_ARG ] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        check_admin_referer( self::PURGE_DISMISS_ARG );
        delete_transient( self::PURGE_REPORT_TRANSIENT );

        wp_safe_redirect( remove_query_arg( [ self::PURGE_DISMISS_ARG, '_wpnonce' ] ) );
        exit;
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
     * Determines whether guests may read user records over the REST API.
     */
    private function blocks_user_enumeration(): bool {
        if ( is_user_logged_in() ) {
            return false;
        }

        return Options::is( 'restrict_rest_users' ) || Options::is( 'block_author_enumeration' );
    }

    /**
     * Builds the transient key that counts failed logins for the connecting address.
     */
    private static function login_throttle_key(): string {
        // REMOTE_ADDR only: forwarded-for headers are supplied by the client and cannot be trusted.
        $address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        /**
         * Allows a site behind a trusted reverse proxy to supply the real client address.
         *
         * @param string $address Address used to group failed login attempts.
         */
        $address = (string) apply_filters( 'ks_bootstrapper_login_throttle_ip', $address );

        return '' === $address ? '' : self::LOGIN_TRANSIENT_PREFIX . md5( $address );
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
     * Reports whether the running web server reads the config files written into uploads.
     */
    private static function server_reads_uploads_config(): bool {
        global $is_apache, $is_IIS, $is_iis7;

        return (bool) $is_apache || (bool) $is_IIS || (bool) $is_iis7;
    }

    /**
     * Writes managed server config files into uploads to block PHP execution.
     */
    private static function protect_uploads_directory(): bool {
        if ( ! Options::is( 'harden_uploads', true ) ) {
            return false;
        }

        $uploads = wp_upload_dir();
        $base_dir = trailingslashit( (string) ( $uploads['basedir'] ?? '' ) );

        if ( ! empty( $uploads['error'] ) || '' === $base_dir || ! is_dir( $base_dir ) ) {
            return false;
        }

        $htaccess_file   = $base_dir . '.htaccess';
        $web_config_file = $base_dir . 'web.config';

        /*
         * Repair runs before the server-type gate on purpose. A guard file this plugin
         * already wrote can have been left mode 0600 by tempnam(), and an .htaccess that
         * Apache cannot read makes the whole uploads tree answer 500. The host serving the
         * site today is not necessarily the host serving it tomorrow, so the repair has to
         * be reachable even where a guard file would never be created in the first place.
         */
        self::repair_managed_uploads_file( $htaccess_file, '# BEGIN ' . self::HTACCESS_MARKER );
        self::repair_managed_uploads_file( $web_config_file, self::WEB_CONFIG_RULE );

        // Apache and IIS read these files; nginx, Caddy and FrankenPHP never do.
        if ( ! self::server_reads_uploads_config() ) {
            return false;
        }

        if ( ! wp_is_writable( $base_dir ) ) {
            return false;
        }

        $protected = false;

        $marker_start = '# BEGIN ' . self::HTACCESS_MARKER;
        $marker_end   = '# END ' . self::HTACCESS_MARKER;
        $block        = implode(
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

        if ( $updated_contents === $contents ) {
            self::ensure_file_is_readable( $htaccess_file );
            $protected = true;
        } elseif ( self::atomic_write( $htaccess_file, $updated_contents ) ) {
            $protected = true;
        }

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
        $temporary = tempnam( $directory, 'ks_bootstrapper_' );

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

        // tempnam() creates the staging file as 0600 and rename() keeps that mode.
        self::ensure_file_is_readable( $path );

        return true;
    }

    /**
     * Restores read access to a guard file this plugin itself wrote into uploads.
     *
     * Only a file carrying our own signature is touched, so an .htaccess or web.config
     * written by the host or by hand is left exactly as it is.
     *
     * @param string $path      Absolute path of the managed file.
     * @param string $signature Text that identifies the file as one this plugin manages.
     */
    private static function repair_managed_uploads_file( string $path, string $signature ): void {
        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            return;
        }

        $contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( ! is_string( $contents ) || ! str_contains( $contents, $signature ) ) {
            return;
        }

        self::ensure_file_is_readable( $path );
    }

    /**
     * Grants the web server user read access to a managed file, the way core does.
     */
    private static function ensure_file_is_readable( string $path ): void {
        if ( ! file_exists( $path ) ) {
            return;
        }

        $permissions = fileperms( $path );

        if ( false === $permissions ) {
            return;
        }

        // fileperms() also reports the file type, which chmod() must never be handed.
        $mode = $permissions & 07777;

        if ( 0644 !== ( $mode & 0644 ) ) {
            chmod( $path, $mode | 0644 );
        }
    }

    /**
     * Writes debug messages only when WP_DEBUG is enabled.
     */
    private static function debug_log( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KS Bootstrapper Security: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    /**
     * Recursively removes PHP-like files from uploads.
     */
    private static function delete_php_files_from_uploads(): int {
        if ( ! Options::is( 'purge_php_from_uploads' ) ) {
            return 0;
        }

        $uploads = wp_upload_dir();
        $base_dir = (string) ( $uploads['basedir'] ?? '' );

        if ( ! empty( $uploads['error'] ) || '' === $base_dir || ! is_dir( $base_dir ) ) {
            return 0;
        }

        $removed = [];
        $checked = 0;
        $started = microtime( true );

        try {
            /*
             * CATCH_GET_CHILD keeps one unreadable subdirectory from throwing an
             * UnexpectedValueException that would abort activation or the cron run.
             */
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
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

                if ( self::is_directory_guard( $file ) ) {
                    continue;
                }

                $pathname = $file->getPathname();

                /*
                 * Unlinking needs write access to the parent directory, not to the file,
                 * so a read-only dropped shell must not be skipped. wp_delete_file()
                 * reports the outcome by itself.
                 */
                if ( ! wp_delete_file( $pathname ) ) {
                    self::debug_log( 'Failed to remove blocked file from uploads: ' . $pathname );
                    continue;
                }

                $removed[] = $pathname;
                self::debug_log( 'Removed blocked file from uploads: ' . $pathname );
            }
        } catch ( Throwable $exception ) {
            self::debug_log( 'Uploads cleanup stopped: ' . $exception->getMessage() );
        }

        if ( $removed ) {
            self::record_purge_report( $removed );
        }

        return count( $removed );
    }

    /**
     * Detects the blank "Silence is golden" files plugins drop to suppress directory listing.
     */
    private static function is_directory_guard( SplFileInfo $file ): bool {
        if ( 'index.php' !== $file->getFilename() || $file->getSize() > self::GUARD_FILE_MAX_SIZE ) {
            return false;
        }

        $contents = (string) @file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        return 1 === preg_match( '#^\s*<\?php\s*(?://|/\*|\#)?\s*Silence is golden#i', $contents );
    }

    /**
     * Stores the list of purged files so it can be surfaced in the dashboard.
     *
     * @param string[] $removed Absolute paths of the deleted files.
     */
    private static function record_purge_report( array $removed ): void {
        $previous = get_transient( self::PURGE_REPORT_TRANSIENT );
        $report   = is_array( $previous ) ? array_merge( $previous, $removed ) : $removed;

        set_transient( self::PURGE_REPORT_TRANSIENT, array_slice( array_unique( $report ), 0, 50 ), MONTH_IN_SECONDS );
    }

    /**
     * Removes the Exif/XMP and IPTC segments from a JPEG file in place.
     *
     * Keeps APP0 (JFIF) and APP2 (ICC colour profile) so the image still renders
     * with the colours it was uploaded with, and never re-encodes the pixels.
     */
    private static function strip_jpeg_app_segments( string $path ): bool {
        if ( ! is_file( $path ) ) {
            return false;
        }

        $contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if ( ! is_string( $contents ) || "\xFF\xD8" !== substr( $contents, 0, 2 ) ) {
            return false;
        }

        $length  = strlen( $contents );
        $offset  = 2;
        $output  = "\xFF\xD8";
        $changed = false;

        while ( $offset < $length ) {
            if ( "\xFF" !== $contents[ $offset ] || $offset + 1 >= $length ) {
                return false;
            }

            $marker = ord( $contents[ $offset + 1 ] );

            // Padding byte before the real marker.
            if ( 0xFF === $marker ) {
                $output .= "\xFF";
                ++$offset;
                continue;
            }

            // End of image: copy whatever trails it untouched.
            if ( 0xD9 === $marker ) {
                $output .= substr( $contents, $offset );
                break;
            }

            // Start of scan: the rest of the file is entropy-coded image data.
            if ( 0xDA === $marker ) {
                $output .= substr( $contents, $offset );
                break;
            }

            // Standalone markers carry no payload.
            if ( 0x01 === $marker || ( $marker >= 0xD0 && $marker <= 0xD8 ) ) {
                $output .= substr( $contents, $offset, 2 );
                $offset += 2;
                continue;
            }

            $header = unpack( 'n', substr( $contents, $offset + 2, 2 ) );
            $size   = is_array( $header ) ? (int) $header[1] : 0;

            if ( $size < 2 || $offset + 2 + $size > $length ) {
                return false;
            }

            // APP1 holds Exif and XMP, APP13 holds the IPTC/Photoshop block.
            if ( 0xE1 === $marker || 0xED === $marker ) {
                $changed = true;
            } else {
                $output .= substr( $contents, $offset, 2 + $size );
            }

            $offset += 2 + $size;
        }

        if ( ! $changed ) {
            return false;
        }

        if ( ! self::atomic_write( $path, $output ) ) {
            self::debug_log( 'Unable to rewrite JPEG without metadata: ' . $path );

            return false;
        }

        return true;
    }

}
