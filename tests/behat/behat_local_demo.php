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
 * Steps definitions for the local_demo memo board.
 *
 * @package     local_demo
 * @category    test
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Custom Behat steps for the local_demo memo board.
 *
 * @package     local_demo
 * @category    test
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_demo extends behat_base {

    /**
     * Pin the memo with the given title.
     *
     * @When I pin the memo :title
     * @param string $title the memo title shown on the board
     */
    public function i_pin_the_memo(string $title): void {
        $this->press_memo_button($title, get_string('pin', 'local_demo'));
    }

    /**
     * Unpin the memo with the given title.
     *
     * @When I unpin the memo :title
     * @param string $title the memo title shown on the board
     */
    public function i_unpin_the_memo(string $title): void {
        $this->press_memo_button($title, get_string('unpin', 'local_demo'));
    }

    /**
     * Check that one memo appears above another in the list.
     *
     * @Then I should see memo :a above memo :b
     * @param string $a title expected higher in the list
     * @param string $b title expected lower in the list
     */
    public function memo_should_appear_above(string $a, string $b): void {
        $titles = $this->memo_titles_in_order();

        $posa = array_search($a, $titles, true);
        $posb = array_search($b, $titles, true);

        if ($posa === false) {
            throw new ExpectationException("Memo \"$a\" was not found on the board.", $this->getSession());
        }
        if ($posb === false) {
            throw new ExpectationException("Memo \"$b\" was not found on the board.", $this->getSession());
        }
        if ($posa >= $posb) {
            throw new ExpectationException(
                "Memo \"$a\" should appear above \"$b\", but it does not.",
                $this->getSession()
            );
        }
    }

    /**
     * Read the memo titles from the board, top to bottom.
     *
     * @return string[] memo titles in display order
     */
    protected function memo_titles_in_order(): array {
        $nodes = $this->getSession()->getPage()->findAll('css', 'ul.local-demo-memos > li h4');

        $titles = [];
        foreach ($nodes as $node) {
            $titles[] = trim($node->getText());
        }
        return $titles;
    }

    /**
     * Find the memo row by title and press its pin/unpin button.
     *
     * @param string $title the memo title shown on the board
     * @param string $label the button text to press (Pin or Unpin)
     */
    protected function press_memo_button(string $title, string $label): void {
        $rows = $this->getSession()->getPage()->findAll('css', 'ul.local-demo-memos > li');

        foreach ($rows as $row) {
            $heading = $row->find('css', 'h4');
            if ($heading && trim($heading->getText()) === $title) {
                $button = $row->find('named', ['button', $label]);
                if (!$button) {
                    throw new ExpectationException(
                        "Memo \"$title\" has no \"$label\" button.",
                        $this->getSession()
                    );
                }
                $button->press();
                return;
            }
        }

        throw new ExpectationException("Memo \"$title\" was not found on the board.", $this->getSession());
    }
}
