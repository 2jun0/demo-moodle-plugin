# Behat 가이드 — 왜 생겼나, 어떻게 도나, 어떻게 쓰나

이 문서는 이 플러그인의 `tests/behat/memo.feature`를 예제로 삼아 Behat을 처음부터
설명합니다. 세 부분으로 나뉩니다.

1. [Behat이 생겨난 이유](#1-behat이-생겨난-이유)
2. [동작 원리](#2-동작-원리)
3. [어떻게 사용하는가](#3-어떻게-사용하는가)

마지막에 [PHPUnit과의 차이](#4-phpunit과-behat-언제-무엇을)와 [참고 링크](#참고)를 둡니다.

---

## 1. Behat이 생겨난 이유

### TDD에서 BDD로

- **TDD(Test-Driven Development)** 는 "코드를 짜기 전에 테스트를 먼저 쓴다"는 방법론입니다.
  하지만 테스트가 `assertEquals(42, $result)`처럼 **개발자 언어**로만 적혀 있어서, 기획자·QA·
  고객은 "이 테스트가 무슨 요구사항을 보장하는지" 읽을 수 없었습니다.
- 2006년경 **Dan North**가 이 간극을 메우려고 **BDD(Behavior-Driven Development)** 를
  제안합니다. 핵심은 "테스트를 **사람이 읽는 문장**(행위 명세)으로 쓰자"는 것이었습니다.
  그래서 등장한 문장 틀이 바로 **Given / When / Then**(주어진 상황 / 행동하면 / 결과는)입니다.

### Cucumber → Behat

- BDD를 코드로 처음 대중화한 도구가 Ruby의 **Cucumber**(2008)였고, 이때 문장을 적는 언어로
  **Gherkin**이 만들어졌습니다.
- PHP 진영에는 **Konstantin Kudryashov(everzet)** 가 Cucumber에서 영감을 받아
  **Behat**(2010~2011년경)을 만들었습니다. Cucumber와 **같은 Gherkin 언어**를 쓰고,
  40여 개 자연어를 지원하는 Gherkin 파서를 PHP로 가져온 것이 Behat입니다.

### 왜 "테스트가 곧 문서"인가

Behat 시나리오는 그 자체로 사람이 읽는 명세이자 자동 실행되는 테스트입니다. 즉
**"살아있는 문서(living documentation)"** 입니다. 요구사항이 바뀌면 시나리오가 깨지므로,
문서와 실제 동작이 어긋나는 사고를 막아줍니다.

### Moodle이 Behat을 채택한 이유

- Moodle은 전 세계 수많은 기여자가 함께 만드는 거대한 LMS입니다. 버튼 하나 옮겨도
  여기저기서 화면이 깨질 수 있어, **클릭→입력→결과 확인**을 사람이 매번 손으로 검증하는 건
  불가능에 가깝습니다.
- 그래서 Moodle은 **2.5(2013년 5월, David Monllao 주도)** 부터 Behat 기반
  **인수테스트(acceptance test)** 를 코어에 통합했습니다. PHPUnit이 "함수·클래스 단위"를
  검증한다면, Behat은 **실제 브라우저로 Moodle을 띄워 사용자처럼 조작**해 화면 동작을
  검증합니다.
- 이 플러그인이 Behat 테스트를 둔 이유도 같습니다: "메모가 폼으로 작성되어 목록에
  **실제로 뜨는가**"를 데이터 계층이 아니라 **UI 흐름 전체**로 증명하기 위해서입니다.

---

## 2. 동작 원리

### 전체 그림 (레이어)

```
.feature 파일 (Gherkin, 사람이 읽는 문장)
        │  각 줄(step)을 정규식으로 매칭
        ▼
Step 정의 (PHP context 클래스의 메서드, @Given/@When/@Then)
        │  "이 문장이면 이 동작을 해라"
        ▼
Mink (브라우저 조작 추상화 계층)
        │  방문/클릭/입력/텍스트확인 을 드라이버에 위임
        ▼
드라이버 ── 비-JS: BrowserKit/Goutte (HTTP만, 빠름)
        └── JS:   Selenium + 실제 Chrome (자바스크립트 실행)
        ▼
테스트용 Moodle 사이트 (별도 DB·dataroot로 격리)
```

요점은 **"문장 → 코드 → 브라우저 → 진짜 Moodle"** 로 한 단계씩 내려간다는 것입니다.

### Gherkin 문법

`memo.feature`에 쓰인 키워드들의 의미:

| 키워드 | 뜻 |
|--------|----|
| `Feature` | 이 기능이 무엇이고 왜 필요한지 (서술, 실행 안 됨) |
| `Scenario` | 하나의 구체적 시험 사례 |
| `Given` | **사전 조건** (이미 이런 상태다 — 로그인됨, 데이터 존재 등) |
| `When` | **행동** (사용자가 무언가를 한다 — 입력·클릭) |
| `Then` | **기대 결과** (화면에 ~가 보여야 한다) |
| `And` / `But` | 바로 위 키워드(Given/When/Then)를 이어서 쓰는 접속사 |
| `Background` | 모든 시나리오 앞에 공통으로 도는 사전 조건 |
| `Scenario Outline` + `Examples` | 같은 시나리오를 표의 값으로 여러 번 반복 |

태그(`@local @local_demo`)는 시나리오를 분류·필터링하는 라벨입니다.

### Step은 어떻게 코드와 연결되나

`.feature`의 각 줄은 마법이 아니라, **PHP로 미리 정의된 스텝**과 정규식으로 매칭됩니다.
예를 들어 `And I visit "/local/demo/index.php"` 줄은 Moodle 코어의 다음 메서드에 연결됩니다
(개념적 예):

```php
// lib/tests/behat/behat_general.php
/**
 * @When I visit :localurl
 */
public function i_visit(string $localurl): void {
    $this->execute('behat_general::i_am_on_homepage'); // 등 실제 동작
    // ...$localurl 로 이동
}
```

- `:localurl` 자리에 `"/local/demo/index.php"`가 들어갑니다.
- 그래서 **스텝 문구는 영어로, 따옴표 안 값은 자유롭게**(한글 포함) 쓰는 것입니다.
  영어 정규식에 매칭돼야 하기 때문에, 우리 시나리오의 데이터("다음 주 시험")는 한글이지만
  스텝 문구(`I set the field`, `I press`)는 영어로 둔 것입니다.

Moodle의 스텝 정의는 주로 `lib/tests/behat/behat_*.php`에 있습니다
(`behat_general`, `behat_forms`, `behat_navigation`, `behat_admin` 등). 플러그인이 자체
스텝을 추가하려면 `tests/behat/behat_<component>.php`에 둡니다.

### 격리된 테스트 사이트

Behat은 **운영/개발 DB를 절대 건드리지 않습니다.** Moodle의 `config.php`에 별도 설정으로
완전히 분리된 테스트 사이트를 씁니다:

```php
$CFG->behat_wwwroot  = 'http://127.0.0.1:8000';
$CFG->behat_dataroot = '/path/to/behat_moodledata'; // 별도 파일 저장소
$CFG->behat_prefix   = 'beh_';                       // 별도 DB 테이블 접두사
```

`Given I log in as "admin"` 같은 스텝은 이 격리 사이트에서 실행됩니다.

### JS 시나리오 vs 비-JS 시나리오

- 시나리오에 `@javascript` 태그가 **있으면** → 진짜 Chrome(Selenium/WebDriver)으로 돌려
  자바스크립트가 실행됩니다(드래그앤드롭, AJAX 등 필요할 때).
- **없으면** → 빠른 비-JS 드라이버(HTTP 요청만)로 돕니다.

우리 `memo.feature`에는 `@javascript`가 없습니다. 메모 작성은 단순 폼 제출 + 서버 렌더라
JS가 필요 없기 때문입니다. (폼의 필수값 검증은 클라이언트 + **서버 양쪽**에서 걸리므로,
JS 없이도 빈 값은 서버에서 막힙니다.) 필요 없는 `@javascript`를 붙이지 않는 것이 권장
사항이며 테스트도 더 빠릅니다.

### 데이터 생성기

테스트 시작 시 필요한 데이터(코스·유저·등록)는 손으로 클릭하지 않고 **데이터 생성 스텝**으로
만듭니다. 예:

```gherkin
Given the following "users" exist:
  | username | firstname | lastname |
  | teacher1 | Teacher   | One      |
And the following "courses" exist:
  | fullname | shortname |
  | 코스 1   | C1        |
```

이 스텝들은 `lib/tests/behat/behat_data_generators.php`가 처리합니다. 우리 시나리오는
관리자 로그인만 쓰므로 별도 생성기가 필요 없습니다(admin은 기본 존재 + 모든 권한 우회).

---

## 3. 어떻게 사용하는가

### 우리 `memo.feature` 한 줄씩 해부

```gherkin
@local @local_demo                                  # ① 태그
Feature: 데모 메모판에 메모 올리기                    # ② 기능 설명(서술)
  빠른 공지를 공유하기 위해
  사이트 관리자로서
  메모를 올리고 목록에서 확인할 수 있어야 한다

  Scenario: 관리자가 메모를 올리면 목록에 보인다       # ③ 시나리오 제목
    Given I log in as "admin"                       # ④ 사전조건: 관리자 로그인
    And I visit "/local/demo/index.php"             # ⑤ 메모판 페이지로 이동
    When I set the field "Title" to "다음 주 시험"    # ⑥ 제목 입력
    And I set the field "Content" to "계산기를 꼭 가져오세요"  # ⑦ 내용 입력
    And I press "Add a memo"                        # ⑧ 저장 버튼 클릭
    Then I should see "다음 주 시험"                  # ⑨ 결과: 목록에 제목이 보임
    And I should see "계산기를 꼭 가져오세요"          # ⑩ 결과: 목록에 내용이 보임
```

- **①** `@local_demo`가 핵심 태그입니다. `moodle-plugin-ci`는 이 **컴포넌트 태그**로
  "이 플러그인의 시나리오만" 골라 돌립니다.
- **⑥~⑧** `"Title"`, `"Content"`, `"Add a memo"`는 화면에 렌더된 **라벨/버튼 텍스트**와
  글자까지 똑같아야 합니다. 이 값들은 `lang/en/local_demo.php`의 문자열에서 옵니다.
  라벨을 바꾸면 시나리오도 같이 바꿔야 합니다.
- **⑨~⑩** `I should see`는 페이지 전체 텍스트를 훑어 해당 문자열이 있는지 봅니다. 저장 후
  `index.php`가 메모 목록을 다시 그리며 방금 쓴 한글 텍스트를 출력하므로 통과합니다.

### 파일 위치·이름 규칙

- 시나리오 파일: `<plugin>/tests/behat/*.feature`
- 자체 스텝(있다면): `<plugin>/tests/behat/behat_<component>.php`
- 첫 줄(또는 각 시나리오 위)에 **컴포넌트 태그**(`@local_demo`)를 붙여야 CI가 인식합니다.

### 자주 쓰는 스텝 (플러그인 작성용 치트시트)

```gherkin
Given I log in as "admin"
Given I am on site homepage
When I visit "/local/demo/index.php"
When I set the field "라벨" to "값"
When I press "버튼텍스트"
When I click on "링크텍스트" "link"
When I follow "링크텍스트"
Then I should see "텍스트"
Then I should not see "텍스트"
Then I should see "텍스트" in the "#region" "css_element"
```

전체 스텝 목록은 자기 Moodle 사이트의
**사이트관리 → 개발 → 인수테스트(Acceptance testing)** 화면이나 아래 참고 링크에서 볼 수
있습니다.

### 로컬에서 직접 돌리려면

> ⚠️ Behat은 **Moodle 코어 + DB가 설치된 개발 환경**에서만 돕니다. 이 플러그인 저장소
> 하나만으로는 실행되지 않습니다(그래서 우리는 CI에서 돌립니다).

개발용 Moodle이 있다면:

```bash
# 1) config.php 에 behat_* 설정 추가 (위 '격리된 테스트 사이트' 참고)

# 2) Behat 테스트 환경 1회 초기화 (별도 테스트 사이트 설치 + behat.yml 생성)
php admin/tool/behat/cli/init.php

# 3) 우리 플러그인 시나리오만 실행
php admin/tool/behat/cli/run.php --tags=@local_demo
#   또는
vendor/bin/behat --config /path/to/behat_moodledata/behatrun/behat/behat.yml --tags=@local_demo
```

### CI(GitHub Actions)에서 도는 방식

이 저장소의 `.github/workflows/moodle-ci.yml`은 마지막 단계에서 이렇게 돕니다:

```yaml
- name: Behat features (브라우저 테스트)
  run: moodle-plugin-ci behat --profile chrome
```

`moodle-plugin-ci`가 Moodle 코어 설치 → 테스트 사이트 초기화 → Chrome 기동 →
`@local_demo` 시나리오 실행까지 알아서 처리합니다. **실제 통과/실패는 Actions 로그에서**
확인합니다.

### 실패하면 어떻게 디버깅하나

- `vendor/bin/behat ... -v` (또는 `-vvv`)로 상세 로그를 봅니다.
- Behat은 실패 시점의 **HTML/스크린샷을 덤프**합니다
  (`$CFG->behat_faildump_path`에 저장). 화면이 기대와 어떻게 달랐는지 그대로 볼 수 있습니다.
- 흔한 실패 원인:
  - 라벨/버튼 텍스트가 시나리오와 **한 글자라도 다름** → `lang` 문자열과 대조.
  - 권한 부족으로 페이지 진입 실패 → 로그인 사용자/권한 확인.
  - 정말 JS가 필요한데 `@javascript`를 안 붙임 → 태그 추가.

### 새 시나리오 추가하기

1. `tests/behat/`에 `.feature`를 만들고 맨 위에 `@local_demo` 태그.
2. `Scenario:`를 적고 Given/When/Then으로 흐름을 기술.
3. 화면 라벨과 정확히 일치하는 스텝 문구 사용.
4. push → CI의 Behat 단계에서 자동 실행.

---

## 4. PHPUnit과 Behat: 언제 무엇을

| | PHPUnit | Behat |
|---|---------|-------|
| 검증 대상 | 함수·클래스·DB 로직 (**내부**) | 사용자 화면 흐름 (**바깥**) |
| 속도 | 빠름 | 느림(브라우저) |
| 예시(이 플러그인) | `memo::create()`가 DB에 저장하는가 | 폼으로 메모를 올리면 목록에 뜨는가 |
| 언어 | PHP 코드 | Gherkin 문장 |
| 권장 비중 | 많이(로직 대부분) | 핵심 사용자 흐름 위주로 적게 |

원칙: **로직은 PHPUnit으로 촘촘히, 사용자 시나리오는 Behat으로 핵심만.** 이 플러그인은
두 계층을 각각 한 개씩 두어 "메모가 잘 올라간다"를 데이터·UI 양면에서 증명합니다.

---

## 참고

- [Behat — Quick Intro (공식 문서)](https://docs.behat.org/en/v2.5/quick_intro.html)
- [Behat/Gherkin 파서 (GitHub)](https://github.com/Behat/Gherkin)
- [Behat | Cucumber 문서](https://cucumber.io/docs/installation/php/)
- [Moodle Behat 통합 (개발자 문서)](https://moodledev.io/general/development/tools/behat)
- [Moodle — Writing acceptance tests](https://docs.moodle.org/dev/Writing_acceptance_tests)
- [Moodle — Running acceptance tests](https://moodledev.io/general/development/tools/behat/running)
