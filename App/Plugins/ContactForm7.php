<?php
/**
 * Contact Form 7 integration and cleanup.
 *
 * @package    WP_Bootstrapper
 * @subpackage Plugins
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Plugins;

use WP_Post;
use WPB\Attributes\Hook;
use WPB\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class ContactForm7 {

    /**
     * Checks whether Contact Form 7 is active.
     */
    private function is_active(): bool {
        return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7' );
    }

    /**
     * Contact Form 7 default CSS output.
     *
     * @param bool $load Whether Contact Form 7 should load its CSS.
     *
     * @return bool
     */
    #[Hook( 'wpcf7_load_css' )]
    public function default_css( bool $load ): bool {
        if ( ! $this->is_active() || Options::is( 'cf7_default_css', true ) ) {
            return $load;
        }

        return false;
    }

    /**
     * Automatic paragraph wrapping for Contact Form 7 forms.
     *
     * @param bool $use_autop Whether Contact Form 7 should auto-wrap markup.
     *
     * @return bool
     */
    #[Hook( 'wpcf7_autop_or_not' )]
    public function autop( bool $use_autop ): bool {
        if ( ! $this->is_active() || Options::is( 'cf7_autop', true ) ) {
            return $use_autop;
        }

        return false;
    }

    /**
     * Populates a hidden "referer-page" form tag with the current referer URL.
     *
     * @param array $form_tag Contact Form 7 form tag config.
     *
     * @return array
     */
    #[Hook( 'wpcf7_form_tag' )]
    public function inject_referer_page( array $form_tag ): array {
        if (
            ! $this->is_active() ||
            is_admin() ||
            ! Options::is( 'cf7_referer_page_tag', true ) ||
            ! isset( $form_tag['name'] ) ||
            'referer-page' !== $form_tag['name']
        ) {
            return $form_tag;
        }

        $raw_referer = wp_get_raw_referer();
        $referer     = is_string( $raw_referer ) ? esc_url_raw( $raw_referer ) : '';

        if ( '' === $referer || ! wp_http_validate_url( $referer ) ) {
            return $form_tag;
        }

        $form_tag['values']   ??= [];
        $form_tag['values'][] = $referer;

        return $form_tag;
    }

    /**
     * Retrieves Contact Form 7 forms as select options.
     *
     * @return array<string, string>
     */
    public static function get_forms_as_select_options(): array {
        if ( ! defined( 'WPCF7_VERSION' ) && ! class_exists( 'WPCF7' ) ) {
            return [];
        }

        $forms = get_posts( [
            'post_type'              => 'wpcf7_contact_form',
            'posts_per_page'         => - 1,
            'post_status'            => 'publish',
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ] );

        if ( empty( $forms ) || ! is_array( $forms ) ) {
            return [];
        }

        $options = [];

        /** @var WP_Post $form */
        foreach ( $forms as $form ) {
            $id    = isset( $form->ID ) ? (string) $form->ID : '';
            $title = isset( $form->post_title ) ? wp_kses_post( $form->post_title ) : '';

            if ( '' !== $id && '' !== $title ) {
                $options[ $id ] = $title;
            }
        }

        return $options;
    }

    /**
     * Renders a Contact Form 7 form with an optional heading.
     */
    public static function render_form( int $id, bool|string $heading = false ): void {
        if ( $id <= 0 || ( ! defined( 'WPCF7_VERSION' ) && ! class_exists( 'WPCF7' ) ) ) {
            return;
        }

        $output = '';

        if ( is_string( $heading ) && '' !== trim( $heading ) ) {
            $output .= sprintf(
                '<h3 class="mb-4">%s</h3>',
                esc_html( $heading )
            );
        }

        $output .= do_shortcode( sprintf( '[contact-form-7 id="%d"]', $id ) );

        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

}
