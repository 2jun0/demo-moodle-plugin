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
 * Unit tests for the local_demo memo API.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_demo;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for {@see \local_demo\memo}.
 *
 * @covers \local_demo\memo
 */
class memo_test extends \advanced_testcase {

    /**
     * Creating a memo stores it and makes it retrievable.
     */
    public function test_create_stores_memo(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $id = memo::create('Exam next week', 'Bring your calculator', $user->id);

        $record = $DB->get_record('local_demo_memos', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Exam next week', $record->title);
        $this->assertSame('Bring your calculator', $record->content);
        $this->assertEquals($user->id, $record->usermodified);
        $this->assertGreaterThan(0, $record->timecreated);

        $this->assertCount(1, memo::get_all());
    }
}
