<?php
/**
 * Core Settings Builder
 *
 * Orchestrates the creation, rendering, and processing of WordPress admin settings pages
 * using a modern, fluent, and strictly typed Object-Oriented approach. It stores all
 * configuration fields in a single database option array to optimize database queries.
 *
 * @package    KS_Bootstrapper
 * @subpackage Settings
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper\Settings;

use KonstantinSorokin\Bootstrapper\Settings\Definitions\Tab;
use KonstantinSorokin\Bootstrapper\Settings\Definitions\Field;
use KonstantinSorokin\Bootstrapper\Settings\Enums\FieldType;
use KonstantinSorokin\Bootstrapper\Settings\Helpers\Options;

defined( 'ABSPATH' ) || exit;

class Settings {

    /**
     * Collection of registered tabs.
     *
     * @var Tab[]
     */
    public private(set) array $tabs = [];

    /**
     * Initializes the settings builder.
     */
    public function __construct(
        public private(set) string $optionName,
        public private(set) string $pageId,
        public private(set) string $title,
        public private(set) string $menuName = '',
        public private(set) string $parentSlug = 'options-general.php'
    ) {
        if ( empty( $this->menuName ) ) {
            $this->menuName = $this->title;
        }
    }

    /**
     * Static factory method to instantiate the builder fluently.
     */
    public static function make( string $optionName, string $pageId, string $title ): static {
        return new static( $optionName, $pageId, $title );
    }

    /**
     * Registers a new tab within the settings page.
     */
    public function add_tab( string $id, string $name, callable $buildSections ): self {
        $tab = new Tab( $id, $name );
        $buildSections( $tab );
        $this->tabs[] = $tab;

        return $this;
    }

    /**
     * Conditionally executes a callback on the builder.
     */
    public function when( bool $condition, callable $callback ): self {
        if ( $condition ) {
            $callback( $this );
        }

        return $this;
    }

    /**
     * Hooks the settings builder into the WordPress lifecycle.
     */
    public function boot(): void {
        Options::register_defaults( $this->optionName, $this->defaults() );

        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Collects the default value declared for every registered field.
     *
     * Values are normalised to the type the field stores, so a checkbox that declares no
     * default reports false rather than an empty string.
     *
     * @return array Map of field id to declared default value.
     */
    public function defaults(): array {
        $defaults = [];

        foreach ( $this->tabs as $tab ) {
            foreach ( $tab->sections as $section ) {
                foreach ( $section->fields as $field ) {
                    $defaults[ $field->id ] = match ( $field->type ) {
                        FieldType::CHECKBOX => (bool) $field->default,
                        FieldType::NUMBER => is_numeric( $field->default ) ? (int) $field->default : 0,
                        default => is_scalar( $field->default ) ? (string) $field->default : '',
                    };
                }
            }
        }

        return $defaults;
    }

    /**
     * Callback for the 'admin_menu' hook.
     */
    public function add_menu_page(): void {
        add_submenu_page(
            $this->parentSlug,
            esc_html( $this->title ),
            esc_html( $this->menuName ),
            'manage_options',
            $this->pageId,
            [ $this, 'render_page' ]
        );
    }

    /**
     * Callback for the 'admin_init' hook.
     */
    public function register_settings(): void {
        register_setting( $this->pageId, $this->optionName, [ $this, 'sanitize_settings' ] );

        foreach ( $this->tabs as $tabIndex => $tab ) {
            $pageParam = $this->pageId . '_tab_' . $tabIndex;

            foreach ( $tab->sections as $section ) {
                $sectionId = $this->pageId . '_' . $section->id;

                add_settings_section(
                    $sectionId,
                    esc_html( $section->title ),
                    function () use ( $section ) {
                        if ( ! empty( $section->description ) ) {
                            echo '<p class="description">' . esc_html( $section->description ) . '</p>';
                        }
                    },
                    $pageParam
                );

                foreach ( $section->fields as $field ) {
                    add_settings_field(
                        $field->id,
                        ! empty( $field->label ) ? sprintf( '<label for="%s">%s</label>', esc_attr( $field->id ), esc_html( $field->label ) ) : '',
                        fn() => $this->render_field( $field ),
                        $pageParam,
                        $sectionId
                    );
                }
            }
        }
    }

    /**
     * Renders the HTML markup for an individual settings field.
     */
    private function render_field( Field $field ): void {
        $options = get_option( $this->optionName, [] );
        $value   = $options[ $field->id ] ?? $field->default;

        $nameAttr = esc_attr( "{$this->optionName}[{$field->id}]" );
        $fieldId  = esc_attr( $field->id );
        $class    = 'regular-text';

        $html = match ( $field->type ) {
            FieldType::TEXTAREA => sprintf(
                '<textarea id="%s" name="%s" rows="5" class="%s">%s</textarea>',
                $fieldId,
                $nameAttr,
                $class,
                esc_textarea( (string) $value )
            ),
            FieldType::SELECT => sprintf(
                '<select id="%s" name="%s" class="%s">%s</select>',
                $fieldId,
                $nameAttr,
                $class,
                implode( '',
                    array_map(
                        fn( $val, $label ) => sprintf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $value, $val, false ), esc_html( $label ) ),
                        array_keys( $field->options ),
                        $field->options
                    ) )
            ),
            FieldType::CHECKBOX => sprintf(
                '<input id="%s" type="checkbox" name="%s" value="1" %s><label for="%s">%s</label>',
                $fieldId,
                $nameAttr,
                checked( $value, true, false ),
                $fieldId,
                esc_html( (string) $field->labelCheckbox )
            ),
            default => sprintf(
                '<input id="%s" type="%s" name="%s" value="%s" placeholder="%s" class="%s">',
                $fieldId,
                esc_attr( $field->type->value ),
                $nameAttr,
                esc_attr( (string) $value ),
                esc_attr( (string) $field->placeholder ),
                $class
            ),
        };

        if ( ! empty( $field->description ) ) {
            $html .= '<p class="description">' . esc_html( (string) $field->description ) . '</p>';
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Sanitizes the unified settings array before storage.
     *
     * The stored row is the starting point, and only the fields that were actually in scope are
     * rewritten: the submitted tab when the value comes from the settings form, and the supplied
     * keys alone when another piece of code calls update_option(). Without this, saving one tab
     * would blank every field the form did not render, since an unchecked checkbox and an
     * unrendered field are equally absent from the submitted data.
     */
    public function sanitize_settings( mixed $input ): array {
        $input = is_array( $input ) ? $input : [];

        $stored    = get_option( $this->optionName, [] );
        $sanitized = is_array( $stored ) ? $stored : [];

        // Set by the settings form; null for programmatic writes.
        $scope = isset( $input['__tab'] ) ? sanitize_key( (string) $input['__tab'] ) : null;

        foreach ( $this->tabs as $tab ) {
            if ( null !== $scope && $tab->id !== $scope ) {
                continue;
            }

            foreach ( $tab->sections as $section ) {
                foreach ( $section->fields as $field ) {
                    // Programmatic write: leave the keys the caller did not supply alone.
                    if ( null === $scope && ! array_key_exists( $field->id, $input ) ) {
                        continue;
                    }

                    $raw = $input[ $field->id ] ?? null;

                    $sanitized[ $field->id ] = match ( $field->type ) {
                        FieldType::CHECKBOX => ! empty( $raw ),
                        FieldType::NUMBER => is_numeric( $raw ) ? max( 1, (int) $raw ) : 0,
                        FieldType::URL => esc_url_raw( (string) ( $raw ?? '' ) ),
                        default => sanitize_text_field( (string) ( $raw ?? '' ) ),
                    };
                }
            }
        }

        unset( $sanitized['__tab'] );

        return $sanitized;
    }

    /**
     * Resolves the index of the tab requested through ?tab=, falling back to the first tab.
     */
    private function current_tab_index(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
        $requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

        if ( '' !== $requested ) {
            foreach ( $this->tabs as $index => $tab ) {
                if ( $tab->id === $requested ) {
                    return $index;
                }
            }
        }

        return 0;
    }

    /**
     * Builds the admin URL of a tab. The first tab owns the bare page URL.
     */
    private function tab_url( int $index ): string {
        $url = menu_page_url( $this->pageId, false );

        if ( empty( $url ) ) {
            $url = add_query_arg( 'page', $this->pageId, admin_url( $this->parentSlug ) );
        }

        return 0 === $index ? $url : add_query_arg( 'tab', $this->tabs[ $index ]->id, $url );
    }

    /**
     * Outputs the structural HTML for the settings page.
     *
     * Tabs are plain links driven by ?tab=, exactly as the core admin screens do it, so the
     * current tab is linkable, survives Back and Refresh, is returned to after saving, and needs
     * no JavaScript to be reachable.
     */
    public function render_page(): void {
        $current = $this->current_tab_index();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $this->title ) . '</h1>';

        if ( count( $this->tabs ) > 1 ) {
            printf(
                '<nav class="nav-tab-wrapper wp-clearfix" aria-label="%s">',
                esc_attr__( 'Secondary menu', 'ks-bootstrapper' )
            );

            foreach ( $this->tabs as $index => $tab ) {
                printf(
                    '<a href="%s" class="nav-tab%s"%s>%s</a>',
                    esc_url( $this->tab_url( $index ) ),
                    $index === $current ? ' nav-tab-active' : '',
                    $index === $current ? ' aria-current="page"' : '',
                    esc_html( $tab->name )
                );
            }

            echo '</nav>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields( $this->pageId );

        if ( isset( $this->tabs[ $current ] ) ) {
            // Tells sanitize_settings() which tab's fields were actually submitted.
            printf(
                '<input type="hidden" name="%s[__tab]" value="%s">',
                esc_attr( $this->optionName ),
                esc_attr( $this->tabs[ $current ]->id )
            );

            do_settings_sections( $this->pageId . '_tab_' . $current );
        }

        submit_button();
        echo '</form></div>';
    }

}
