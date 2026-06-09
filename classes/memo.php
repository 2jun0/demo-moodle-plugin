<?php

/**
 * Memo board API for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 */

namespace local_demo;

defined('MOODLE_INTERNAL') || die();

/**
 * API for creating and listing memos.
 */
class memo {

    /**
     * Create a memo.
     *
     * @param string $title memo title
     * @param string $content memo body
     * @param int|null $userid author user id; defaults to the current user
     * @return int id of the created memo
     */
    public static function create(string $title, string $content, ?int $userid = null): int {
        global $DB, $USER;

        $record = new \stdClass();
        $record->title = $title;
        $record->content = $content;
        $record->timecreated = time();
        $record->usermodified = $userid ?? (int) $USER->id;

        return (int) $DB->insert_record('local_demo_memos', $record);
    }

    /**
     * Get all memos, pinned first, then newest first.
     *
     * @return array array of memo records keyed by id
     */
    public static function get_all(): array {
        global $DB;

        return $DB->get_records('local_demo_memos', null, 'pinned DESC, timecreated DESC, id DESC');
    }

    /**
     * Pin or unpin a memo.
     *
     * @param int $id memo id
     * @param bool $pinned true to pin to the top, false to unpin
     */
    public static function set_pinned(int $id, bool $pinned): void {
        global $DB;

        $DB->set_field('local_demo_memos', 'pinned', $pinned ? 1 : 0, ['id' => $id]);
    }
}
