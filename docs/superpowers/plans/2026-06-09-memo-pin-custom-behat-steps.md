# 메모 고정(pin) + 커스텀 Behat 스텝 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 메모를 상단 고정/해제하는 기능을 추가하고, 내장 스텝으로는 검증이 어색한 "행 지정 클릭"과 "목록 순서 비교"를 플러그인 자체 Behat 스텝(`@When`/`@Then`)으로 정의해 검증한다.

**Architecture:** `local_demo_memos`에 `pinned` 컬럼을 더한다. `\local_demo\memo`가 정렬(`pinned DESC, timecreated DESC, id DESC`)과 `set_pinned()`로 데이터 계층을 담당하고, `index.php`가 메모마다 sesskey 보호 POST 폼(Pin/Unpin 버튼)을 렌더한다. PHPUnit이 정렬·고정 로직을, 커스텀 Behat 컨텍스트(`behat_local_demo`)가 UI 흐름을 검증한다.

**Tech Stack:** PHP, Moodle local 플러그인 API (XMLDB, upgrade.php, capabilities, html_writer, sesskey), PHPUnit(`advanced_testcase`), Behat + Mink(`behat_base`), moodle-plugin-ci.

---

## 실행 환경에 대한 중요 메모

이 저장소는 **플러그인 코드만** 있고 Moodle 본체·DB가 없다. 따라서 PHPUnit/Behat를 **로컬에서 바로 실행할 수 없다.** 각 task의 검증은 다음으로 한다:

- **로컬 검증:** `php -l <file>` 로 PHP 구문 오류만 확인한다.
- **실제 테스트 실행:** push 후 GitHub Actions(moodle-plugin-ci)에서 돌거나, 별도로 구축한 로컬 moodle-plugin-ci 환경에서 `moodle-plugin-ci phpunit` / `moodle-plugin-ci behat` 로 돈다.

`php` 가 PATH에 없으면 구문 검사 단계는 건너뛰고 push 후 CI 로그로 확인한다. 작업 브랜치는 `feature/memo-pin`(이미 설계 문서가 커밋되어 있음)을 그대로 쓴다.

## 공통 GPL 헤더 (모든 PHP 파일 맨 위에 그대로 붙인다)

아래 15줄은 모든 신규/수정 PHP 파일의 첫 줄부터 동일하게 들어간다. 각 task의 코드 블록은 이 헤더 **다음에 오는 내용**만 보여준다(헤더 중복을 피하려는 것일 뿐, 실제 파일엔 반드시 포함). **예외:** 커스텀 Behat 컨텍스트 파일(`behat_local_demo.php`)은 GPL 헤더 다음에 `MOODLE_INTERNAL` 검사 없이 곧장 `require_once`로 시작한다(Task 5에 명시).

```php
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
```

---

## Task 1: 버전 올림 + 언어 문자열 추가

**Files:**
- Modify: `version.php:28,31`
- Modify: `lang/en/local_demo.php` (마지막 `$string` 줄들 뒤에 추가)

- [ ] **Step 1: 버전·릴리스 상향**

`version.php`에서 두 줄을 바꾼다(`component`, `requires`, `maturity`는 그대로).

바꾸기 전:

```php
$plugin->version = 2026060801;
```
```php
$plugin->release = 'v1.0.0';
```

바꾼 후:

```php
$plugin->version = 2026060901;
```
```php
$plugin->release = 'v1.1.0';
```

- [ ] **Step 2: 고정 관련 언어 문자열 추가**

`lang/en/local_demo.php`의 `$string['privacy:metadata'] = ...;` 줄 **바로 위**(또는 권한 문자열 근처)에 아래 세 줄을 추가한다. 기존 문자열은 그대로 둔다.

```php
$string['pin'] = 'Pin';
$string['unpin'] = 'Unpin';
$string['pinnedbadge'] = 'Pinned';
```

- [ ] **Step 3: 구문 검사**

Run: `php -l version.php && php -l lang/en/local_demo.php`
Expected: 각 파일에 대해 `No syntax errors detected`

- [ ] **Step 4: 커밋**

```bash
git add version.php lang/en/local_demo.php
git commit -m "feat: 메모 고정 기능 위한 버전 상향·언어 문자열 추가"
```

---

## Task 2: DB 스키마 — install.xml 수정 + upgrade.php 신규

**Files:**
- Modify: `db/install.xml`
- Create: `db/upgrade.php`

- [ ] **Step 1: install.xml에 pinned 필드 추가**

`db/install.xml`에서 (a) 루트 `<XMLDB ... VERSION="2026060801"`을 `VERSION="2026060901"`로 바꾸고, (b) `usermodified` FIELD 줄 **바로 다음**에 `pinned` FIELD를 추가한다. 결과 `<FIELDS>` 블록은 아래와 정확히 같아야 한다.

```xml
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="title" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="content" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="usermodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="pinned" TYPE="int" LENGTH="1" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
```

- [ ] **Step 2: upgrade.php 작성**

`db/upgrade.php` 생성 (공통 GPL 헤더 + 아래). 기존 설치를 위한 업그레이드 경로다. CI는 항상 새 설치라 install.xml만 타지만, savepoints 검사 및 운영 호환을 위해 둔다. savepoint 버전(`2026060901`)은 `version.php`의 버전과 정확히 일치해야 한다.

```php

/**
 * Upgrade steps for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_demo plugin.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_local_demo_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060901) {

        // Define field pinned to be added to local_demo_memos.
        $table = new xmldb_table('local_demo_memos');
        $field = new xmldb_field('pinned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'usermodified');

        // Conditionally launch add field pinned.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Demo memo board savepoint reached.
        upgrade_plugin_savepoint(true, 2026060901, 'local', 'demo');
    }

    return true;
}
```

- [ ] **Step 3: XML 적격성 + PHP 구문 검사**

Run: `python3 -c "import xml.dom.minidom; xml.dom.minidom.parse('db/install.xml'); print('well-formed')" && php -l db/upgrade.php`
Expected: `well-formed` 와 `No syntax errors detected`
(python3 이 없으면 XML 단계는 건너뛰고 CI의 validate 단계에서 확인한다.)

- [ ] **Step 4: 커밋**

```bash
git add db/install.xml db/upgrade.php
git commit -m "feat: local_demo_memos에 pinned 필드와 업그레이드 경로 추가"
```

---

## Task 3: 메모 API — 정렬 변경 + set_pinned (TDD)

**Files:**
- Modify: `tests/memo_test.php` (메서드 2개 추가)
- Modify: `classes/memo.php` (`get_all` 수정 + `set_pinned` 추가)

- [ ] **Step 1: 실패하는 PHPUnit 테스트 2개 추가**

`tests/memo_test.php`의 마지막 메서드(`test_create_stores_memo`) 닫는 `}` 다음, 클래스 닫는 `}` **앞**에 아래 두 메서드를 추가한다. 기존 메서드는 그대로 둔다.

```php

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
```

- [ ] **Step 2: 테스트가 (구문상) 유효한지 확인 + 실패 예상 근거**

Run: `php -l tests/memo_test.php`
Expected: `No syntax errors detected`
실제 실행 시: `test_set_pinned_floats_to_top` 은 `memo::set_pinned()` 가 아직 없어 **FAIL**(메서드 없음), `test_get_all_breaks_time_ties_by_id` 는 `get_all()` 이 `id DESC` 타이브레이크를 안 해 **FAIL/비결정**한다.

- [ ] **Step 3: get_all 정렬 변경 + set_pinned 구현**

`classes/memo.php`의 `get_all()` 본문 한 줄을 바꾸고, `get_all()` 메서드 닫는 `}` 다음(클래스 닫는 `}` 앞)에 `set_pinned()`를 추가한다.

`get_all()` 안에서 바꾸기 전:

```php
        return $DB->get_records('local_demo_memos', null, 'timecreated DESC');
```

바꾼 후:

```php
        return $DB->get_records('local_demo_memos', null, 'pinned DESC, timecreated DESC, id DESC');
```

`get_all()` 다음에 추가:

```php

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
```

- [ ] **Step 4: 구문 검사**

Run: `php -l classes/memo.php`
Expected: `No syntax errors detected`
실제 실행 시 위 두 테스트와 기존 `test_create_stores_memo` 가 모두 **PASS** 한다(CI/로컬 환경에서 확인).

- [ ] **Step 5: 커밋**

```bash
git add tests/memo_test.php classes/memo.php
git commit -m "feat: 메모 고정 정렬과 set_pinned API + PHPUnit 테스트 추가"
```

---

## Task 4: index.php — pin/unpin 처리 + 고정 버튼/배지

**Files:**
- Modify: `index.php` (capability 검사 이후 처리 추가 + 목록 렌더 교체)

- [ ] **Step 1: pin/unpin POST 처리 추가**

`index.php`에서 `$PAGE->set_heading(...)` 줄 다음, `$form = new \local_demo\form\memo_form();` 줄 **바로 앞**에 아래 블록을 삽입한다. capability는 위쪽 `require_capability('local/demo:postmemo', ...)`로 이미 통과한 상태다.

```php
$pin = optional_param('pin', 0, PARAM_INT);
$unpin = optional_param('unpin', 0, PARAM_INT);
if ($pin || $unpin) {
    require_sesskey();
    \local_demo\memo::set_pinned($pin ?: $unpin, (bool) $pin);
    redirect(new moodle_url('/local/demo/index.php'));
}

```

- [ ] **Step 2: 목록 렌더 교체 (고정 버튼/배지 포함)**

`index.php`의 `} else {` 부터 목록을 그리는 부분 — 즉 아래 기존 블록 전체

```php
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
```

을 아래 전체로 바꾼다.

```php
} else {
    echo html_writer::start_tag('ul', ['class' => 'local-demo-memos']);
    foreach ($memos as $memo) {
        $liattributes = ['class' => 'local-demo-memo' . ($memo->pinned ? ' local-demo-memo-pinned' : '')];
        echo html_writer::start_tag('li', $liattributes);
        echo html_writer::tag('h4', format_string($memo->title));
        if ($memo->pinned) {
            echo html_writer::span(get_string('pinnedbadge', 'local_demo'), 'badge local-demo-pinned-badge');
        }
        echo html_writer::tag('div', format_text($memo->content, FORMAT_PLAIN));
        echo html_writer::tag(
            'div',
            get_string('postedby', 'local_demo', userdate($memo->timecreated)),
            ['class' => 'local-demo-memo-meta']
        );

        // Pin / unpin toggle: a small POST form carrying sesskey and the memo id.
        $fieldname = $memo->pinned ? 'unpin' : 'pin';
        $label = $memo->pinned ? get_string('unpin', 'local_demo') : get_string('pin', 'local_demo');
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/demo/index.php'))->out(false),
            'class' => 'local-demo-pin-form',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $fieldname, 'value' => $memo->id]);
        echo html_writer::tag('button', $label, ['type' => 'submit']);
        echo html_writer::end_tag('form');

        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
}
```

참고: `$memo->pinned` 는 DB에서 문자열 `'0'`/`'1'` 로 오는데 PHP에서 `'0'` 은 falsy 라 `if ($memo->pinned)` 가 의도대로 동작한다. 메모 작성 폼 제출(POST)에는 moodleform 의 숨김 토큰이 있어 `optional_param('pin')` 와 충돌하지 않는다.

- [ ] **Step 3: 구문 검사**

Run: `php -l index.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: 커밋**

```bash
git add index.php
git commit -m "feat: 메모판에 고정/해제 버튼과 고정 배지 추가"
```

---

## Task 5: 커스텀 Behat 스텝 — behat_local_demo.php

**Files:**
- Create: `tests/behat/behat_local_demo.php`

- [ ] **Step 1: 커스텀 컨텍스트 작성**

`tests/behat/behat_local_demo.php` 생성. 공통 GPL 헤더 다음에 (MOODLE_INTERNAL 검사 없이) 아래 내용을 그대로 붙인다. `behat_base` 경로의 `../` 4개는 `local/demo/tests/behat/` 에서 Moodle 루트까지 거슬러 올라가는 깊이다.

```php

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
```

- [ ] **Step 2: 구문 검사**

Run: `php -l tests/behat/behat_local_demo.php`
Expected: `No syntax errors detected`
(`require_once`/`use` 가 있어도 `php -l` 은 토큰 단계만 보므로 통과한다. 실제 스텝 등록·동작은 CI Behat 단계에서 확인한다.)

- [ ] **Step 3: 커밋**

```bash
git add tests/behat/behat_local_demo.php
git commit -m "test: 메모 고정/순서 검증용 커스텀 Behat 스텝 추가"
```

---

## Task 6: Behat 시나리오 — pin.feature

**Files:**
- Create: `tests/behat/pin.feature`

- [ ] **Step 1: feature 작성**

`tests/behat/pin.feature` 생성. 필드/버튼 라벨("Title"/"Content"/"Add a memo")은 화면 문자열과 정확히 일치한다. `I pin the memo` / `I should see memo ... above memo ...` 는 Task 5에서 정의한 커스텀 스텝이다.

```gherkin
@local @local_demo
Feature: 데모 메모판에서 메모 고정하기
  중요한 공지를 위로 띄우기 위해
  사이트 관리자로서
  메모를 상단에 고정하고 해제할 수 있어야 한다

  Background:
    Given I log in as "admin"
    And I visit "/local/demo/index.php"
    And I set the field "Title" to "지난 공지"
    And I set the field "Content" to "지난주 안내입니다"
    And I press "Add a memo"
    And I set the field "Title" to "새 공지"
    And I set the field "Content" to "이번주 안내입니다"
    And I press "Add a memo"

  Scenario: 고정한 메모가 최신 메모보다 위로 올라온다
    Then I should see memo "새 공지" above memo "지난 공지"
    When I pin the memo "지난 공지"
    Then I should see memo "지난 공지" above memo "새 공지"

  Scenario: 고정을 해제하면 다시 최신순으로 돌아온다
    When I pin the memo "지난 공지"
    And I unpin the memo "지난 공지"
    Then I should see memo "새 공지" above memo "지난 공지"
```

참고: `Background` 는 각 시나리오 앞에서 매번 도므로 두 시나리오 모두 메모 2개가 준비된 상태에서 시작한다. 메모 작성은 단순 폼 POST + 서버 렌더라 `@javascript` 가 필요 없다.

- [ ] **Step 2: 문법 확인**

Behat feature는 PHP가 아니라 `php -l` 대상이 아니다. 텍스트가 위와 정확히 일치하는지 눈으로 확인하고, 실제 실행은 CI(`moodle-plugin-ci behat --profile chrome`)에서 한다.

- [ ] **Step 3: 커밋**

```bash
git add tests/behat/pin.feature
git commit -m "test: 메모 고정/해제 순서 Behat 시나리오 추가"
```

---

## Task 7: behat-guide.md — 커스텀 스텝 섹션 추가

**Files:**
- Modify: `docs/behat-guide.md` (section 3의 "### 새 시나리오 추가하기" 다음에 하위 섹션 추가)

- [ ] **Step 1: 가이드에 커스텀 스텝 하위 섹션 추가**

`docs/behat-guide.md`에서 "### 새 시나리오 추가하기" 블록(번호 목록 1~4까지)이 끝난 직후, 그 아래 `---` 구분선 **앞**에 아래 하위 섹션을 통째로 삽입한다. 기존 텍스트는 건드리지 않는다.

````markdown

### 커스텀 스텝 직접 정의하기 (`pin.feature` 예제)

내장 스텝만으로 안 되는 순간이 옵니다. 메모 고정 기능의 `pin.feature`가 그 예입니다.

- 메모마다 "Pin" 버튼이 렌더되므로 화면에 같은 라벨 버튼이 여러 개입니다. 내장
  `I press "Pin"`은 **어느 행의 버튼인지** 못 집습니다.
- "메모 A가 메모 B보다 **위에** 보인다"는 **순서 비교**는 내장 스텝에 아예 없습니다.

그래서 `tests/behat/behat_local_demo.php`에 자체 스텝을 정의합니다(`behat_base` 상속,
실패 시 `ExpectationException`):

```php
class behat_local_demo extends behat_base {

    /** @When I pin the memo :title */
    public function i_pin_the_memo(string $title): void {
        $this->press_memo_button($title, get_string('pin', 'local_demo'));
    }

    /** @Then I should see memo :a above memo :b */
    public function memo_should_appear_above(string $a, string $b): void {
        $titles = $this->memo_titles_in_order();      // 위→아래 제목 배열
        $posa = array_search($a, $titles, true);
        $posb = array_search($b, $titles, true);
        if ($posa === false || $posb === false || $posa >= $posb) {
            throw new ExpectationException("...", $this->getSession());
        }
    }
}
```

핵심:

- **애너테이션이 곧 스텝 문구**입니다. `@When I pin the memo :title`의 `:title`이
  따옴표 인자(`"지난 공지"`)를 받아 메서드 인자로 들어옵니다.
- 행을 집는 헬퍼는 Mink로 `ul.local-demo-memos > li`를 훑어 `<h4>` 제목이 일치하는 행을
  찾고, 그 **행 안에서만** `named` 셀렉터로 버튼을 눌러 "그 메모의" 버튼임을 보장합니다.
- 버튼 라벨은 `get_string('pin', ...)`로 가져옵니다. 화면 라벨이 바뀌어도 스텝과 자동으로
  맞아, "스텝 문구는 영어, 데이터는 자유"(2장) 원칙을 깨지 않습니다.
- 이 시나리오는 비-JS라 `find()`의 스핀/재시도 없이 Mink를 직접 써도 안정적입니다.
  AJAX·동적 렌더가 끼면 `@javascript`를 붙이고 `behat_base::find()`(재시도 포함)를 씁니다.

그러면 `pin.feature`에서 이렇게 사람이 읽는 문장으로 순서를 검증합니다:

```gherkin
When I pin the memo "지난 공지"
Then I should see memo "지난 공지" above memo "새 공지"
```
````

- [ ] **Step 2: 커밋**

```bash
git add docs/behat-guide.md
git commit -m "docs: Behat 가이드에 커스텀 스텝 정의 섹션 추가"
```

---

## 최종 확인 (전체 push 후 CI에서)

- [ ] `moodle-plugin-ci validate` 통과 (새 문자열 `pin`/`unpin`/`pinnedbadge` 명명·구조)
- [ ] `moodle-plugin-ci savepoints` 통과 (upgrade.php 의 savepoint 버전 = version.php 버전)
- [ ] **PHPUnit**: `test_create_stores_memo`, `test_set_pinned_floats_to_top`, `test_get_all_breaks_time_ties_by_id` 3개 PASS
- [ ] **Behat**: `pin.feature` 두 시나리오 + 기존 `memo.feature` 시나리오 PASS
- [ ] 위 결과가 최신 matrix 행(PHP 8.3 + Moodle 4.5)에서 초록인지 Actions 로그로 확인
- [ ] (선택) 로컬 Moodle 개발 환경이 있으면 `php admin/cli/upgrade.php`로 pinned 필드 업그레이드 경로도 한 번 확인
