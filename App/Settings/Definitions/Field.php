<?php
/**
 * Settings Field Definition
 *
 * Represents an individual settings field configuration. Acts as a Data Transfer Object (DTO)
 * to store field attributes, ensuring strict typing and immutability from the outside
 * after instantiation.
 *
 * @package    WP_Bootstrapper
 * @subpackage Settings\Definitions
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Settings\Definitions;

use WPB\Settings\Enums\FieldType;

defined( 'ABSPATH' ) || exit;

class Field {

    /**
     * Initializes a new Field instance.
     *
     * @param string      $id            The unique identifier for the field. Used as the array key in the database
     *                                   and for the HTML ID/name attributes.
     * @param FieldType   $type          The type of the field (e.g., text, checkbox, select). Determines how the field
     *                                   is rendered and sanitized. Defaults to FieldType::TEXT.
     * @param string      $label         The display label rendered next to or above the input field.
     * @param string      $description   Optional helper text rendered below the input field to provide extra context.
     * @param mixed       $default       The fallback default value returned if the user hasn't saved a value for this field yet.
     * @param array       $options       An associative array of key-value pairs (value => label) required exclusively
     *                                   for SELECT field types.
     * @param string|null $placeholder   Optional placeholder text displayed inside text-based input fields.
     * @param string|null $labelCheckbox Optional label text displayed immediately next to a CHECKBOX input,
     *                                   typically used when the main $label describes a group of settings.
     */
    public function __construct(
        public private(set) string $id,
        public private(set) FieldType $type = FieldType::TEXT,
        public private(set) string $label = '',
        public private(set) string $description = '',
        public private(set) mixed $default = '',
        public private(set) array $options = [],
        public private(set) ?string $placeholder = null,
        public private(set) ?string $labelCheckbox = null,
    ) {}

}