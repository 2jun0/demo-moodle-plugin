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
