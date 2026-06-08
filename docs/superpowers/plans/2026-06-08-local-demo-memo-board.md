# local_demo 메모판 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `local_demo` 플러그인에 "사이트 메모판" 기능과 이를 검증하는 PHPUnit·Behat 테스트를 추가해 moodle-plugin-ci에서 두 테스트가 실제로 도는 것을 보여준다.

**Architecture:** 메모는 `local_demo_memos` 테이블에 저장한다. 순수 `$DB`를 쓰는 `\local_demo\memo` API(create/get_all)가 데이터 계층을 담당하고, `index.php`가 moodleform(`\local_demo\form\memo_form`)으로 작성 폼과 목록을 렌더한다. PHPUnit은 API 계층을, Behat은 페이지 UI를 검증한다.

**Tech Stack:** PHP, Moodle local 플러그인 API (XMLDB, moodleform, capabilities), PHPUnit(`advanced_testcase`), Behat, moodle-plugin-ci.

---

## 실행 환경에 대한 중요 메모

이 저장소는 **플러그인 코드만** 있고 Moodle 본체·DB가 없다. 따라서 PHPUnit/Behat를 **로컬에서 바로 실행할 수 없다.** 각 task의 검증은 다음으로 한다:

- **로컬 검증:** `php -l <file>` 로 PHP 구문 오류만 확인한다.
- **실제 테스트 실행:** push 후 GitHub Actions(moodle-plugin-ci)에서 돌거나, 별도로 구축한 로컬 moodle-plugin-ci 환경에서 `moodle-plugin-ci phpunit` / `moodle-plugin-ci behat` 로 돈다.

`php` 가 PATH에 없으면 구문 검사 단계는 건너뛰고 push 후 CI 로그로 확인한다.

## 공통 GPL 헤더 (모든 PHP 파일 맨 위에 그대로 붙인다)

아래 15줄은 모든 신규/수정 PHP 파일의 첫 줄부터 동일하게 들어간다. 각 task의 코드 블록은 이 헤더 **다음에 오는 내용**만 보여준다(헤더 중복을 피하려는 것일 뿐, 실제 파일엔 반드시 포함).

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

## Task 1: 언어 문자열 · 권한 · 개인정보 provider · 버전

**Files:**
- Modify: `lang/en/local_demo.php`
- Create: `db/access.php`
- Create: `classes/privacy/provider.php`
- Modify: `version.php:28`

- [ ] **Step 1: 언어 문자열 교체**

`lang/en/local_demo.php`의 헤더(1~25줄, `defined('MOODLE_INTERNAL') || die();` 포함)는 그대로 두고, 마지막 `$string['pluginname'] = 'Demo';` 줄을 아래 전체로 교체한다.

```php
$string['pluginname'] = 'Demo memo board';
$string['memoboard'] = 'Memo board';
$string['addmemo'] = 'Add a memo';
$string['title'] = 'Title';
$string['content'] = 'Content';
$string['memoadded'] = 'Memo added.';
$string['nomemos'] = 'No memos yet.';
$string['postedby'] = 'Posted {$a}';
$string['demo:postmemo'] = 'Post a memo on the memo board';
$string['privacy:metadata'] = 'The Demo memo board plugin does not store any personal data about individual users.';
```

- [ ] **Step 2: 권한 정의 파일 작성**

`db/access.php` 생성 (공통 GPL 헤더 + 아래).

```php

/**
 * Capability definitions for the local_demo plugin.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
```

- [ ] **Step 3: 개인정보 provider 작성**

메모는 작성자 id(`usermodified`)를 담지만, 데모 단순화를 위해 null provider를 선언한다(코어 privacy 호환성 테스트 통과용). 실제 운영 플러그인이라면 사용자 데이터를 export/delete하는 정식 provider를 구현해야 한다. `classes/privacy/provider.php` 생성 (공통 GPL 헤더 + 아래).

```php

/**
 * Privacy provider for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
```

- [ ] **Step 4: 버전 올림**

`version.php`에서 `$plugin->version = 2026060800;` 를 다음으로 바꾼다. (`component`, `requires` 등 나머지는 그대로.)

```php
$plugin->version = 2026060801;
```

- [ ] **Step 5: 구문 검사**

Run: `php -l lang/en/local_demo.php && php -l db/access.php && php -l classes/privacy/provider.php && php -l version.php`
Expected: 각 파일에 대해 `No syntax errors detected`

- [ ] **Step 6: 커밋**

```bash
git add lang/en/local_demo.php db/access.php classes/privacy/provider.php version.php
git commit -m "feat: 메모판 언어 문자열·권한·privacy provider 추가"
```

---

## Task 2: DB 스키마

**Files:**
- Create: `db/install.xml`

- [ ] **Step 1: install.xml 작성**

`db/install.xml` 생성 (XML 파일이라 GPL 헤더 없음).

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/demo/db" VERSION="2026060801" COMMENT="XMLDB file for Moodle local/demo plugin."
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="../../../lib/xmldb/xmldb.xsd"
>
  <TABLES>
    <TABLE NAME="local_demo_memos" COMMENT="Stores memos posted on the demo memo board.">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="title" TYPE="char" LENGTH="255" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="content" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="usermodified" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
    </TABLE>
  </TABLES>
</XMLDB>
```

참고: CI는 항상 새 설치라 install.xml 만으로 테이블이 만들어진다. 별도 `db/upgrade.php`는 필요 없다(savepoints 검사는 upgrade.php가 없으면 통과).

- [ ] **Step 2: XML 적격성 확인**

Run: `python3 -c "import xml.dom.minidom,sys; xml.dom.minidom.parse('db/install.xml'); print('well-formed')"`
Expected: `well-formed`
(python3 이 없으면 이 단계는 건너뛰고 CI의 validate 단계에서 확인한다.)

- [ ] **Step 3: 커밋**

```bash
git add db/install.xml
git commit -m "feat: local_demo_memos 테이블 스키마 추가"
```

---

## Task 3: 메모 API 클래스 (TDD)

**Files:**
- Test: `tests/memo_test.php`
- Create: `classes/memo.php`

- [ ] **Step 1: 실패하는 PHPUnit 테스트 작성**

`tests/memo_test.php` 생성 (공통 GPL 헤더 + 아래).

```php

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
```

- [ ] **Step 2: 테스트가 (구문상) 유효한지 확인 + 실패 예상 근거**

Run: `php -l tests/memo_test.php`
Expected: `No syntax errors detected`
실제 실행 시(CI/로컬 moodle-plugin-ci)에는 `\local_demo\memo` 클래스가 아직 없으므로 "Class ... not found"로 **FAIL** 한다. 이 시점에 push해서 CI에서 빨강을 확인해도 된다(선택).

- [ ] **Step 3: 최소 구현 작성**

`classes/memo.php` 생성 (공통 GPL 헤더 + 아래).

```php

/**
 * Memo board API for local_demo.
 *
 * @package     local_demo
 * @copyright   2026 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Get all memos, newest first.
     *
     * @return array array of memo records keyed by id
     */
    public static function get_all(): array {
        global $DB;

        return $DB->get_records('local_demo_memos', null, 'timecreated DESC');
    }
}
```

- [ ] **Step 4: 구문 검사**

Run: `php -l classes/memo.php`
Expected: `No syntax errors detected`
실제 실행 시 `test_create_stores_memo` 가 **PASS** 한다(CI/로컬 환경에서 확인).

- [ ] **Step 5: 커밋**

```bash
git add tests/memo_test.php classes/memo.php
git commit -m "feat: 메모 생성/조회 API와 PHPUnit 테스트 추가"
```

---

## Task 4: 작성 폼 · 페이지

**Files:**
- Create: `classes/form/memo_form.php`
- Create: `index.php`

- [ ] **Step 1: moodleform 작성**

`classes/form/memo_form.php` 생성 (공통 GPL 헤더 + 아래). `global $CFG;` 후 formslib을 불러와 autoload 컨텍스트에서도 `\moodleform` 부모가 로드되도록 한다.

```php

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
```

- [ ] **Step 2: 페이지 작성**

`index.php` 생성 (공통 GPL 헤더 + 아래). `use` 문 대신 완전수식 클래스명을 써서 스크립트 최상단 require 와의 순서 문제를 피한다.

```php

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
```

- [ ] **Step 3: 구문 검사**

Run: `php -l classes/form/memo_form.php && php -l index.php`
Expected: 각 파일에 대해 `No syntax errors detected`

- [ ] **Step 4: 커밋**

```bash
git add classes/form/memo_form.php index.php
git commit -m "feat: 메모 작성 폼과 메모판 페이지 추가"
```

---

## Task 5: Behat 테스트

**Files:**
- Create: `tests/behat/memo.feature`

- [ ] **Step 1: feature 작성**

`tests/behat/memo.feature` 생성. 필드 라벨("Title"/"Content")과 버튼 라벨("Add a memo")은 Task 1·4에서 정한 문자열과 정확히 일치해야 한다.

```gherkin
@local @local_demo
Feature: Posting memos on the demo memo board
  In order to share quick notices
  As a site administrator
  I need to post memos and see them in the list

  Scenario: Administrator posts a memo and sees it listed
    Given I log in as "admin"
    And I visit "/local/demo/index.php"
    When I set the field "Title" to "Exam next week"
    And I set the field "Content" to "Bring your calculator"
    And I press "Add a memo"
    Then I should see "Exam next week"
    And I should see "Bring your calculator"
```

참고: `I visit :url` 스텝은 모던 Moodle(3.10+, 따라서 4.x)에서 제공된다. CI matrix의 최신 행(Moodle 4.5)에서 동작한다.

- [ ] **Step 2: 문법 확인**

Behat feature는 PHP가 아니라 `php -l` 대상이 아니다. 텍스트가 위와 정확히 일치하는지 눈으로 확인하고, 실제 실행은 CI(`moodle-plugin-ci behat --profile chrome`)에서 한다.

- [ ] **Step 3: 커밋**

```bash
git add tests/behat/memo.feature
git commit -m "test: 메모 작성→목록 표시 Behat 시나리오 추가"
```

---

## Task 6: README 수정

**Files:**
- Modify: `README.md` (전체 교체)

- [ ] **Step 1: README 전체 교체**

`README.md` 내용을 아래 전체로 바꾼다.

````markdown
# local_demo — 메모판

Moodle CI 데모용 `local` 플러그인입니다. 간단한 **사이트 메모판** 기능을 갖추고 있으며,
`moodle-plugin-ci`가 GitHub Actions에서 PHPUnit·Behat 테스트를 어떻게 돌리는지 보여줍니다.

## 기능

- `/local/demo/index.php` 페이지에서 제목 + 내용으로 메모를 작성합니다.
- 작성한 메모는 `local_demo_memos` 테이블에 저장되고, 최신순 목록으로 표시됩니다.
- `local/demo:postmemo` 권한이 있는 사용자(기본: manager, 사이트 관리자)만 작성할 수 있습니다.

## 구조

```
local_demo/
├── version.php                       # 플러그인 메타데이터
├── db/install.xml                    # local_demo_memos 테이블 정의
├── db/access.php                     # local/demo:postmemo 권한
├── classes/memo.php                  # 메모 생성/조회 API
├── classes/form/memo_form.php        # 메모 작성 폼
├── classes/privacy/provider.php      # privacy(null) provider
├── index.php                         # 메모판 페이지
├── lang/en/local_demo.php            # 언어 문자열
├── tests/memo_test.php               # PHPUnit 테스트
├── tests/behat/memo.feature          # Behat 테스트
├── .github/workflows/moodle-ci.yml   # GitHub Actions 워크플로우
└── README.md
```

## 두 가지 테스트

- **PHPUnit** (`tests/memo_test.php`): `memo::create()`로 만든 메모가 DB에 제대로
  저장되고 `memo::get_all()`로 조회되는지 — **데이터 계층**을 검증합니다.
- **Behat** (`tests/behat/memo.feature`): 관리자로 로그인 → 메모판 페이지에서 폼 작성 →
  목록에 방금 쓴 메모가 보이는지 — **UI 계층**을 검증합니다.

## GitHub에 올려서 CI 돌리기

1. GitHub에 새 레포 생성 (예: `moodle-local_demo`)
2. 이 폴더 내용을 레포 루트에 넣고 push:

   ```
   git init
   git add .
   git commit -m "Initial local_demo plugin with CI"
   git branch -M main
   git remote add origin https://github.com/<your-account>/moodle-local_demo.git
   git push -u origin main
   ```

3. GitHub 레포 → **Actions** 탭에서 빌드 확인

> push하거나 PR을 열 때마다 워크플로우가 자동 실행됩니다.

## CI matrix에 대한 참고 (정직한 안내)

워크플로우는 PHP 5.6/7.1/7.4/8.3 × Moodle 2.9~4.5 조합을 돕니다. 이 플러그인 코드는
모던 PHP/Moodle(네임스페이스, 타입힌트 등) 기준이고 `version.php`가 Moodle 3.9 이상을
요구하므로:

- **최신 조합(PHP 8.3 + Moodle 4.5)** 에서 PHPUnit·Behat가 **초록색(통과)** 으로 도는 것을
  데모로 보면 됩니다.
- **구버전 조합(PHP 5.6/7.1 · Moodle 2.9/3.2)** 은 설치/실행 단계에서 **빨간색(실패)** 이
  날 수 있습니다. 이는 의도된 상태이며, 최신 조합의 초록 결과에 집중하세요.

(모든 조합을 초록으로 만들고 싶다면 워크플로우의 matrix를 최신 1~2개로 줄이면 됩니다.)
````

- [ ] **Step 2: 커밋**

```bash
git add README.md
git commit -m "docs: 메모판 기능과 두 테스트 반영해 README 갱신"
```

---

## 최종 확인 (전체 push 후 CI에서)

- [ ] `moodle-plugin-ci validate` 통과 (플러그인 구조·문자열·권한 명명)
- [ ] `moodle-plugin-ci savepoints` 통과 (upgrade.php 없음 → 통과)
- [ ] **PHPUnit** 단계에서 `local_demo\memo_test::test_create_stores_memo` PASS
- [ ] **Behat** 단계에서 메모 작성 시나리오 PASS
- [ ] 위 둘이 최신 matrix 행(PHP 8.3 + Moodle 4.5)에서 초록인지 Actions 로그로 확인
