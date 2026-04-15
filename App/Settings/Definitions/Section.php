<?php
/**
 * Settings Section Definition
 *
 * Represents a logical grouping of settings fields within a specific tab.
 * Acts as a structural container and provides a fluent interface for registering fields.
 *
 * @package    WP_Bootstrapper
 * @subpackage Settings\Definitions
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Settings\Definitions;

use WPB\Settings\Enums\FieldType;

defined( 'ABSPATH' ) || exit;

class Section {

    /**
     * Collection of fields assigned to this section.
     *
     * @var Field[]
     */
    public private(set) array $fields = [];

    /**
     * Initializes a new Section instance.
     *
     * @param string $id          The unique identifier for the section (used in the HTML ID attribute).
     * @param string $title       The display title rendered as a heading for the section.
     * @param string $description Optional. A descriptive paragraph rendered below the section title.
     */
    public function __construct(
        public private(set) string $id,
        public private(set) string $title,
        public private(set) string $description = '',
    ) {}

    /**
     * Fluently registers a new field within this section.
     *
     * @param string    $id             The unique identifier for the field (used for the name attribute and database key).
     * @param FieldType $type           The type of the input field (e.g., text, checkbox, select). Defaults to TEXT.
     * @param array     $args           {
     *                                  Optional. Additional arguments to configure the field.
     *
     * @type string     $label          The display label for the input field.
     * @type string     $description    Helper text rendered below the input field.
     * @type mixed      $default        The default value to use if the option has not been saved yet.
     * @type array      $options        An associative array of value/label pairs used for SELECT fields.
     * @type string     $placeholder    The placeholder text for the input field.
     * @type string     $label_checkbox The label text displayed directly next to a CHECKBOX field.
     *                                  }
     * @return self Returns the Section instance for method chaining.
     */
    public function add_field( string $id, FieldType $type = FieldType::TEXT, array $args = [] ): self {
        $this->fields[] = new Field(
            id: $id,
            type: $type,
            label: $args['label'] ?? '',
            description: $args['description'] ?? '',
            default: $args['default'] ?? '',
            options: $args['options'] ?? [],
            placeholder: $args['placeholder'] ?? null,
            labelCheckbox: $args['label_checkbox'] ?? null
        );

        return $this;
    }

}