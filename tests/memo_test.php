<?php

/**
 * Unit tests for the local_demo memo API.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
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

    /**
     * Pinning a memo floats it above newer memos; unpinning drops it back.
     */
    public function test_set_pinned_floats_to_top(): void {
        global $DB;
        $this->resetAfterTest();

        $older = (int) $DB->insert_record('local_demo_memos', (object) [
            'title' => 'Older', 'content' => 'a', 'timecreated' => 1000, 'usermodified' => 0, 'pinned' => 0,
        ]);
        $newer = (int) $DB->insert_record('local_demo_memos', (object) [
            'title' => 'Newer', 'content' => 'b', 'timecreated' => 2000, 'usermodified' => 0, 'pinned' => 0,
        ]);

        // By default the newest memo is first.
        $this->assertSame([$newer, $older], array_keys(memo::get_all()));

        // Pinning the older memo floats it to the top.
        memo::set_pinned($older, true);
        $this->assertSame([$older, $newer], array_keys(memo::get_all()));

        // Unpinning drops it back below the newer memo.
        memo::set_pinned($older, false);
        $this->assertSame([$newer, $older], array_keys(memo::get_all()));
    }

    /**
     * Memos with the same timecreated are ordered by id descending (newest insert first).
     */
    public function test_get_all_breaks_time_ties_by_id(): void {
        global $DB;
        $this->resetAfterTest();

        $first = (int) $DB->insert_record('local_demo_memos', (object) [
            'title' => 'First', 'content' => 'a', 'timecreated' => 1000, 'usermodified' => 0, 'pinned' => 0,
        ]);
        $second = (int) $DB->insert_record('local_demo_memos', (object) [
            'title' => 'Second', 'content' => 'b', 'timecreated' => 1000, 'usermodified' => 0, 'pinned' => 0,
        ]);

        // Same timecreated: the later insert (higher id) comes first.
        $this->assertSame([$second, $first], array_keys(memo::get_all()));
    }
}
