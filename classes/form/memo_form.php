<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Memo creation form for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
