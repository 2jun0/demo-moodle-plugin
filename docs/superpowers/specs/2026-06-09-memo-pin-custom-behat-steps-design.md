# local_demo 메모 고정(pin) + 커스텀 Behat 스텝 — 설계 문서

작성일: 2026-06-09

## 목표

기존 메모판에 **메모 상단 고정(pin)** 기능을 더한다. 진짜 목표는 기능 자체가 아니라
**내장 Behat 스텝으로는 검증이 어색한 동작**을 만들어, 플러그인이 자기 Behat 스텝을
직접 정의하는 경험(`tests/behat/behat_<component>.php`의 `@When`/`@Then`)을 만드는 것이다.
`behat-guide.md`는 "자체 스텝은 여기 둔다"고만 안내하고 실제 예제가 없었는데, 이 작업으로
그 예제를 채운다.

## 무엇을 만드나

로그인한 권한 사용자가 메모를 **상단 고정/해제**할 수 있다. 목록 정렬은
`고정 메모 먼저 → 그다음 최신순`. 권한은 기존 `local/demo:postmemo`를 그대로 재사용한다
(메모를 올릴 수 있는 사람이면 고정도 가능). 범위는 기존과 동일하게 사이트 단위 한 페이지.

### "직접 스텝을 정의해야 하는" 이유

- 메모마다 "Pin" 버튼이 렌더되므로 같은 라벨 버튼이 여러 개다. 내장 `I press "Pin"`은
  **어느 행의 버튼인지 못 집는다** → 제목으로 행을 찾아 누르는 커스텀 `@When`이 필요.
- "메모 A가 메모 B보다 위에 보인다"는 **순서 비교**는 내장 스텝에 없다 → 목록 순서를 읽어
  단언하는 커스텀 `@Then`이 필요.

## 파일 구조

```
version.php                        # 버전 2026060801 → 2026060901
db/install.xml                     # local_demo_memos에 pinned 필드 추가 (신규 설치)
db/upgrade.php                     # [신규] 기존 설치에 pinned 필드 추가
classes/memo.php                   # get_all() 정렬 변경 + set_pinned() 추가
index.php                          # pin/unpin POST 처리 + 메모별 고정 버튼/배지
lang/en/local_demo.php             # pin/unpin/pinned 등 문자열 추가
tests/memo_test.php                # PHPUnit: set_pinned + 정렬 결정성 검증
tests/behat/behat_local_demo.php   # [신규] 커스텀 @When/@Then 스텝
tests/behat/pin.feature            # [신규] Background + 고정/해제 시나리오 2개
docs/behat-guide.md                # "커스텀 스텝 정의하기" 섹션 추가
```

## 데이터 모델

테이블 `local_demo_memos`에 한 필드 추가:

| 필드 | 타입 | 설명 |
|------|------|------|
| pinned | INT(1), not null, default 0 | 상단 고정 여부 (1=고정) |

- `db/install.xml`: `usermodified` 다음에 `pinned` FIELD 추가.
- `db/upgrade.php`: `xmldb_local_demo_upgrade($oldversion)` — 신규 버전 미만이고 `pinned`
  필드가 없으면 `add_field`로 추가 후 `upgrade_plugin_savepoint`. (신규 설치는 install.xml만
  타지만, 운영 설치 갱신 경로를 정직하게 갖춘다.)

## 컴포넌트별 책임

### classes/memo.php (`namespace local_demo;`)
- `get_all(): array` — 정렬을 `'pinned DESC, timecreated DESC, id DESC'`로 변경.
  `id DESC` 동률 처리로 같은 초에 만든 메모도 결정적으로 최신 우선이 되어 테스트가 안 흔들린다.
- `set_pinned(int $id, bool $pinned): void` — `$DB->set_field('local_demo_memos', 'pinned',
  $pinned ? 1 : 0, ['id' => $id])`.
- `create()`는 변경 없음 (pinned는 DB default 0).

### index.php
- 페이지 진입부에서 `$pin = optional_param('pin', 0, PARAM_INT)` /
  `$unpin = optional_param('unpin', 0, PARAM_INT)` 읽기. 둘 중 하나라도 있으면:
  `require_sesskey()` → `memo::set_pinned($pin ?: $unpin, (bool)$pin)` → 같은 URL로 redirect.
  (capability는 페이지 상단 `require_capability('local/demo:postmemo', ...)`로 이미 통과.)
- 목록 렌더 시 메모마다 작은 **POST 폼**을 그린다:
  - hidden `sesskey` = `sesskey()`
  - hidden 이름은 고정 상태면 `unpin`, 아니면 `pin`, value = 메모 id
  - `<button type="submit">` 텍스트는 `get_string('unpin'|'pin', 'local_demo')`
- 고정된 메모의 `<li>`에 `local-demo-memo-pinned` 클래스 + "Pinned" 배지(`pinnedbadge`) 추가.
- 목록 `<ul class="local-demo-memos">`, 각 제목 `<h4>`는 기존 구조 유지(커스텀 스텝이 의존).

### lang/en/local_demo.php — 문자열 추가
- `pin` = 'Pin', `unpin` = 'Unpin', `pinnedbadge` = 'Pinned'
- (필요 시 redirect 알림 문자열은 생략하고 조용히 redirect — YAGNI)

## 커스텀 Behat 스텝 — `tests/behat/behat_local_demo.php`

`class behat_local_demo extends behat_base`. `behat_base`(`lib/behat/behat_base.php`) 상속.
실패 시 `Behat\Mink\Exception\ExpectationException`을 던진다. 시나리오가 비-JS라 Mink를
직접 쓴다(스핀/재시도 불필요).

| 스텝 애너테이션 | 메서드 | 동작 |
|------|------|------|
| `@When I pin the memo :title` | `i_pin_the_memo` | 헬퍼로 그 행의 "Pin" 버튼 누름 |
| `@When I unpin the memo :title` | `i_unpin_the_memo` | 그 행의 "Unpin" 버튼 누름 |
| `@Then I should see memo :a above memo :b` | `memo_should_appear_above` | 목록 제목 순서 단언 |

헬퍼:
- `memo_titles_in_order(): array` — `findAll('css', 'ul.local-demo-memos > li h4')`로 제목을
  위→아래 순서로 수집.
- `press_memo_button(string $title, string $label)` — `ul.local-demo-memos > li`를 순회하며
  `<h4>` 텍스트가 `$title`과 일치하는 행을 찾고, 그 안에서 Mink `named` 셀렉터
  `['button', $label]`로 버튼을 찾아 `press()`. 행/버튼 없으면 `ExpectationException`.
- 버튼 라벨은 `get_string('pin'|'unpin', 'local_demo')`로 가져와 **영어 스텝 ↔ 화면 라벨**
  일치를 보장(가이드의 "스텝 문구는 영어, 데이터는 자유" 원칙과 동일). 제목은 한글 데이터를
  따옴표 인자로 받는다.
- `memo_should_appear_above`: `array_search`로 두 제목 위치를 찾아 둘 다 존재하고
  `pos(a) < pos(b)`인지 확인, 아니면 어느 쪽이 문제인지 메시지와 함께 예외.

## Behat 시나리오 — `tests/behat/pin.feature` (`@local @local_demo`)

`Background`로 공통 사전조건 구성:
- admin 로그인 → `/local/demo/index.php` 방문
- "지난 공지" 작성(먼저) → "새 공지" 작성(나중) — 따라서 초기 순서는 최신순(새 공지 위)

시나리오:
- **A. 고정한 메모가 최신 메모보다 위로 올라온다**
  - `Then I should see memo "새 공지" above memo "지난 공지"` (초기 최신순 확인)
  - `When I pin the memo "지난 공지"`
  - `Then I should see memo "지난 공지" above memo "새 공지"` (고정되어 위로)
- **B. 고정을 해제하면 다시 최신순으로 돌아온다**
  - `When I pin the memo "지난 공지"` → `When I unpin the memo "지난 공지"`
  - `Then I should see memo "새 공지" above memo "지난 공지"`

`Background` + 커스텀 `@When`/`@Then`을 모두 쓰는, 기존 단일 시나리오보다 복잡한 테스트.

## PHPUnit 보강 — `tests/memo_test.php` (`advanced_testcase`)

- `test_set_pinned_floats_to_top` — 메모 2개 생성 후 오래된 쪽을 `set_pinned(true)` →
  `get_all()` 첫 번째가 고정 메모인지 검증.
- `test_get_all_orders_by_recency` — 같은 `timecreated`로 2건 직접 insert 후 `get_all()`이
  id 내림차순(나중 insert가 먼저)인지 검증 → 정렬 결정성 보장.

## 문서 — docs/behat-guide.md

"## 5. 커스텀 스텝 직접 정의하기"(가칭) 섹션 추가:
- 왜 내장 스텝으로 안 되는가(같은 라벨 버튼 다수, 순서 비교 부재)
- `behat_local_demo.php`의 `@When`/`@Then` 예제 코드와 동작 설명
- 비-JS에서 Mink 직접 사용, 라벨을 `get_string`으로 맞추는 이유

## 범위에서 제외 (YAGNI)

- 별도 `local/demo:pinmemo` 권한·권한 없는 사용자 시나리오 (기존 권한 재사용으로 결정)
- 고정 개수 제한, 고정 메모 간 정렬 커스터마이즈
- `@javascript` 시나리오 (단순 폼 POST라 비-JS로 충분)
- redirect 성공 알림 문자열
