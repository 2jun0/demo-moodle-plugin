# local_demo

Moodle CI 데모용 최소 `local` 플러그인입니다.
`moodle-plugin-ci`가 GitHub Actions에서 어떻게 도는지 보여주기 위한 용도입니다.

## 구조

```
local_demo/
├── version.php                       # 플러그인 메타데이터
├── lang/en/local_demo.php            # 언어 문자열 (필수)
├── .github/workflows/moodle-ci.yml   # GitHub Actions 워크플로우
└── README.md
```

## GitHub에 올려서 CI 돌리기

1. GitHub에 새 레포 생성 (예: `moodle-local_demo`)
2. 이 폴더 안의 내용을 레포 루트에 그대로 넣고 push:

   ```
   git init
   git add .
   git commit -m "Initial local_demo plugin with CI"
   git branch -M main
   git remote add origin https://github.com/<your-account>/moodle-local_demo.git
   git push -u origin main
   ```

3. GitHub 레포 페이지 → **Actions** 탭에서 빌드가 도는 것 확인

> 푸시하거나 PR을 열 때마다 워크플로우가 자동으로 실행됩니다.

## 데모 팁

- 처음 push하면 모든 검사가 **초록색(통과)** 으로 끝납니다.
- 일부러 표준을 어긴 코드를 넣고 다시 push하면, 해당 검사가
  **빨간색(실패)** 으로 잡히는 걸 보여줄 수 있습니다.
  (예: `version.php`에서 GPL 헤더를 지우거나 짧은 배열 대신 `array()` 사용)
