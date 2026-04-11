<?php
/**
 * Hook Attribute Definition
 *
 * @package    WP_Bootstrapper
 * @subpackage Attributes
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Attributes;

use Attribute;

/**
 * Attribute used to register methods as WordPress hooks.
 * * The IS_REPEATABLE flag allows attaching a single method to multiple different hooks.
 */
#[Attribute( Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE )]
readonly class Hook {

    /**
     * Constructor.
     *
     * @param string $name          The name of the WordPress filter or action (e.g., 'init', 'wp_head').
     * @param int    $priority      Optional. Used to specify the order in which the functions
     *                              associated with a particular action are executed. Default 10.
     * @param int    $accepted_args Optional. The number of arguments the function accepts. Default 1.
     */
    public function __construct(
        public string $name,
        public int $priority = 10,
        public int $accepted_args = 1
    ) {}

}