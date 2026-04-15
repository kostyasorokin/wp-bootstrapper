<?php
/**
 * Core Settings Builder
 *
 * Orchestrates the creation, rendering, and processing of WordPress admin settings pages
 * using a modern, fluent, and strictly typed Object-Oriented approach. It stores all
 * configuration fields in a single database option array to optimize database queries.
 *
 * @package    WP_Bootstrapper
 * @subpackage Settings
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Settings;

use WPB\Settings\Definitions\Tab;
use WPB\Settings\Definitions\Field;
use WPB\Settings\Enums\FieldType;

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
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
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
     */
    public function sanitize_settings( mixed $input ): array {
        $sanitized = [];
        $input     = is_array( $input ) ? $input : [];

        foreach ( $this->tabs as $tab ) {
            foreach ( $tab->sections as $section ) {
                foreach ( $section->fields as $field ) {
                    $raw = $input[ $field->id ] ?? null;

                    $sanitized[ $field->id ] = match ( $field->type ) {
                        FieldType::CHECKBOX => ! empty( $raw ),
                        FieldType::NUMBER => (float) $raw,
                        FieldType::URL => esc_url_raw( $raw ),
                        default => sanitize_text_field( $raw ?? '' ),
                    };
                }
            }
        }

        return $sanitized;
    }

    /**
     * Outputs the structural HTML for the entire settings page.
     */
    public function render_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $this->title ) . '</h1>';

        if ( count( $this->tabs ) > 1 ) {
            echo '<h2 class="nav-tab-wrapper">';
            foreach ( $this->tabs as $index => $tab ) {
                $activeClass = $index === 0 ? ' nav-tab-active' : '';
                echo '<a href="#" class="nav-tab' . esc_attr( $activeClass ) . '" data-tab-index="' . esc_attr( $index ) . '">' . esc_html( $tab->name ) . '</a>';
            }
            echo '</h2>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields( $this->pageId );

        foreach ( $this->tabs as $index => $tab ) {
            $displayStyle = $index === 0 ? 'block' : 'none';
            echo '<div id="tab-content-' . esc_attr( $index ) . '" class="settings-tab-content" style="display: ' . esc_attr( $displayStyle ) . ';">';
            do_settings_sections( $this->pageId . '_tab_' . $index );
            echo '</div>';
        }

        submit_button();
        echo '</form></div>';

        // JS for tab switching with state preservation
        ?>
      <script>
          document.addEventListener('DOMContentLoaded', () => {
              const tabs = document.querySelectorAll('.nav-tab');
              const contents = document.querySelectorAll('.settings-tab-content');
              const storageKey = 'wpb_active_tab_<?php echo esc_js( $this->pageId ); ?>';
              let activeIndex = localStorage.getItem(storageKey) || 0;

              function activateTab(index) {
                  tabs.forEach(t => t.classList.remove('nav-tab-active'));
                  contents.forEach(c => c.style.display = 'none');

                  if (tabs[index] && contents[index]) {
                      tabs[index].classList.add('nav-tab-active');
                      contents[index].style.display = 'block';
                      localStorage.setItem(storageKey, index);
                  }
              }

              tabs.forEach((tab, index) => {
                  tab.addEventListener('click', (e) => {
                      e.preventDefault();
                      activateTab(index);
                  });
              });

              activateTab(activeIndex);
          });
      </script>
        <?php
    }

}