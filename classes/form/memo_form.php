<?php

/**
 * Memo creation form for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 */

namespace local_demo\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for posting a new memo.
 */
class memo_form extends \moodleform {

    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'title', get_string('title', 'local_demo'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('textarea', 'content', get_string('content', 'local_demo'),
            ['rows' => 5, 'cols' => 60]);
        $mform->setType('content', PARAM_TEXT);
        $mform->addRule('content', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('addmemo', 'local_demo'));
    }
}
