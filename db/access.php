<?php

/**
 * Capability definitions for the local_demo plugin.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/demo:postmemo' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
