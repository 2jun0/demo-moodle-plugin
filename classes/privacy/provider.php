<?php

/**
 * Privacy provider for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 */

namespace local_demo\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for local_demo.
 *
 * Demo simplification: declared as a null provider.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Get the language string identifier with the component's static user data reason.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
