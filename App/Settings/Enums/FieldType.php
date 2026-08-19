<?php
/**
 * Field Types Enumeration
 *
 * Defines the available input field types for the settings builder. This enum ensures
 * strict typing and prevents the use of unsupported or invalid HTML input types
 * when generating the plugin's settings forms.
 *
 * @package    KS_Bootstrapper
 * @subpackage Settings\Enums
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace KonstantinSorokin\Bootstrapper\Settings\Enums;

defined( 'ABSPATH' ) || exit;

enum FieldType: string {

    case TEXT = 'text';
    case NUMBER = 'number';
    case URL = 'url';
    case CHECKBOX = 'checkbox';
    case SELECT = 'select';
    case TEXTAREA = 'textarea';

}