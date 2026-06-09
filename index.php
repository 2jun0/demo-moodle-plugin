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
 * Memo board page for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

$context = context_system::instance();
require_capability('local/demo:postmemo', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/demo/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('memoboard', 'local_demo'));
$PAGE->set_heading(get_string('memoboard', 'local_demo'));

$form = new \local_demo\form\memo_form();

if ($data = $form->get_data()) {
    \local_demo\memo::create($data->title, $data->content);
    redirect(
        new moodle_url('/local/demo/index.php'),
        get_string('memoadded', 'local_demo'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addmemo', 'local_demo'));

$form->display();

$memos = \local_demo\memo::get_all();
if (empty($memos)) {
    echo $OUTPUT->notification(get_string('nomemos', 'local_demo'), 'info');
} else {
    echo html_writer::start_tag('ul', ['class' => 'local-demo-memos']);
    foreach ($memos as $memo) {
        echo html_writer::start_tag('li');
        echo html_writer::tag('h4', format_string($memo->title));
        echo html_writer::tag('div', format_text($memo->content, FORMAT_PLAIN));
        echo html_writer::tag(
            'div',
            get_string('postedby', 'local_demo', userdate($memo->timecreated)),
            ['class' => 'local-demo-memo-meta']
        );
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
