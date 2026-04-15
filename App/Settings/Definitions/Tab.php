<?php
/**
 * Settings Tab Definition
 *
 * Represents a distinct navigation tab within the settings page. Acts as a structural
 * container for related settings sections and provides a fluent interface for building them.
 *
 * @package    WP_Bootstrapper
 * @subpackage Settings\Definitions
 * @author     Konstantin Sorokin
 * @link       https://konstantinsorokin.com
 */

namespace WPB\Settings\Definitions;

defined( 'ABSPATH' ) || exit;

class Tab {

    /**
     * Collection of sections assigned to this tab.
     *
     * @var Section[]
     */
    public private(set) array $sections = [];

    /**
     * Initializes a new Tab instance.
     *
     * @param string $id   The unique HTML ID and identifier for the tab.
     * @param string $name The display name shown in the tab navigation.
     */
    public function __construct(
        public private(set) string $id,
        public private(set) string $name,
    ) {}

    /**
     * Fluently adds a new section to this tab.
     *
     * @param string        $id          The unique identifier for the section.
     * @param string        $title       The display title of the section.
     * @param string        $description Optional. A descriptive text rendered below the section title.
     * @param callable|null $buildFields Optional. A closure that receives the created Section instance
     *                                   to fluently add fields to it.
     *                                   * @return self Returns the Tab instance for method chaining.
     */
    public function add_section( string $id, string $title, string $description = '', ?callable $buildFields = null ): self {
        $section = new Section( $id, $title, $description );

        // Execute the closure to populate the section with fields if provided
        if ( $buildFields ) {
            $buildFields( $section );
        }

        $this->sections[] = $section;

        return $this;
    }

    /**
     * Conditionally executes a callback on the tab.
     */
    public function when( bool $condition, callable $callback ): self {
        if ( $condition ) {
            $callback( $this );
        }

        return $this;
    }

}
