# local_demo 메모판 — 설계 문서

작성일: 2026-06-08

## 목표

`local_demo` 플러그인을 "최소 골격"에서 **실제 기능 + PHPUnit/Behat 테스트**를 갖춘
플러그인으로 확장한다. 진짜 목표는 기능 자체가 아니라 **moodle-plugin-ci에서
PHPUnit·Behat 테스트가 실제로 도는 경험**을 만드는 것이다.

## 무엇을 만드나

로그인한 권한 사용자가 짧은 메모(제목 + 내용)를 올리면 목록에 쌓이는 **사이트 메모판**.

- 플러그인 타입: `local` (기존 골격 재사용)
- 컴포넌트명: `local_demo` 유지 (이름 변경 시 디렉터리·CI 경로 churn이 커서 데모 목적엔 현 이름이 안전)
- 범위: 사이트 단위 한 페이지(`/local/demo/index.php`). 코스 id·수강등록 의존이 없어 Behat이 가장 단순·안정적

## 파일 구조

```
version.php                  # 버전 올림 (component/requires는 유지)
db/install.xml               # local_demo_memos 테이블
db/access.php                # local/demo:postmemo 권한
classes/memo.php             # API: create() / get_all() — 순수 $DB
classes/form/memo_form.php   # 제목·내용 입력 moodleform
index.php                    # 메모 목록 + 작성 폼 페이지
lang/en/local_demo.php       # 문자열 (확장)
tests/memo_test.php          # PHPUnit 테스트
tests/behat/memo.feature     # Behat 테스트
README.md                    # 기능/테스트 반영해 수정
```

## 데이터 모델

테이블 `local_demo_memos`:

| 필드 | 타입 | 설명 |
|------|------|------|
| id | BIGINT, PK, auto | |
| title | VARCHAR(255), not null | 메모 제목 |
| content | TEXT, not null | 메모 내용 |
| timecreated | BIGINT(10), not null | 작성 시각(unix) |
| usermodified | BIGINT(10), not null, default 0 | 작성자 user id |

## 컴포넌트별 책임

### classes/memo.php (`namespace local_demo;`)
- `memo::create(string $title, string $content, ?int $userid = null): int`
  - `timecreated = time()`, `usermodified = $userid ?? $USER->id`로 한 행 insert, insert id 반환
- `memo::get_all(): array`
  - `local_demo_memos`를 `timecreated DESC`로 조회해 반환

순수 `$DB` 호출만 사용 — persistent API에 의존하지 않아 버전 호환성을 넓게 가져간다.

### classes/form/memo_form.php (`namespace local_demo\form;`, extends `\moodleform`)
- text `title` (required) + textarea `content` (required) + `add_action_buttons`

### index.php
- `require_login()` 후 `context_system::instance()`에 대해 `require_capability('local/demo:postmemo', ...)`
- `$PAGE` 설정(url/context/title/heading)
- 폼 제출·검증 통과 시 `memo::create()` 호출 → 같은 URL로 redirect + 성공 알림
- 헤더 → 폼 → `memo::get_all()` 목록(제목/내용/시각) 렌더. 메모 없으면 안내 문구

### db/access.php
- `local/demo:postmemo` — write, `CONTEXT_SYSTEM`, manager archetype 허용
  (admin은 사이트관리자 우회로 항상 통과하므로 Behat에서 권한 셋업 불필요)

## 테스트 두 개 — "메모가 잘 올라가는지"를 두 계층에서 증명

### PHPUnit — `tests/memo_test.php` (`advanced_testcase`)
- `resetAfterTest()` 후 `memo::create('Hello', 'World', $user->id)` 호출
- `$DB->get_record('local_demo_memos', ...)`로 title/content/usermodified 일치 검증
- `memo::get_all()`이 1건 반환하는지 검증
- → **데이터 계층** 검증

### Behat — `tests/behat/memo.feature` (`@local @local_demo`)
- admin 로그인 → `/local/demo/index.php` 방문
- "Title", "Content" 필드 입력 → "Save changes" 클릭
- 목록에 방금 쓴 제목·내용이 보이는지 확인
- → **UI 계층** 검증 (모던 Moodle의 `I visit :url` 스텝 사용)

## CI 워크플로우

현재 matrix(PHP 5.6/7.1/7.4/8.3 × Moodle 2.9~4.5)를 **그대로 유지**한다(사용자 결정).

- `version.php`가 Moodle 3.9를 요구하고 코드가 모던 PHP/Moodle 기준이라,
  구버전 조합(PHP 5.6/7.1·Moodle 2.9/3.2)은 설치/실행 단계에서 빨간색이 날 수 있다.
- 최신 조합(PHP 8.3 + Moodle 4.5, moodle-ci `^4`)에서 PHPUnit·Behat가 초록으로 도는 것을 데모로 본다.
- 이 동작은 README에 정직하게 명시한다.

## README 수정 방향

- 플러그인이 이제 "메모판 기능 + PHPUnit/Behat 테스트"를 가진다는 점 반영
- 갱신된 파일 구조
- 두 테스트가 무엇을 검증하는지 한 줄씩
- CI matrix에서 어떤 조합이 초록/빨강으로 기대되는지 정직한 안내

## 범위에서 제외 (YAGNI)

- 메모 수정/삭제 기능 (작성·조회만)
- 코스별 메모, 네비게이션 주입
- 권한 세분화, 알림, 첨부파일
