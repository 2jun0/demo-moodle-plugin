# 로컬에서 moodle-plugin-ci로 Behat 돌리기 (GitHub Actions 없이)

이 문서는 GitHub Actions에 의존하지 않고, **내 PC에서 직접** `moodle-plugin-ci`를
설치해 이 플러그인(`local_demo`)의 **Behat 테스트**를 돌리는 방법을 정리한 것이다.

`.github/workflows/moodle-ci.yml`이 GitHub 러너에서 하던 일을, 똑같은 도구로
**로컬에서 손으로** 재현한다. 자동화 스크립트는 일부러 만들지 않았다 — 한 단계씩
직접 입력하며 무슨 일이 일어나는지 이해하는 것이 목적이다.

> 핵심 단순화 2가지
> - **헤드리스**: 데모 시나리오(`memo.feature`·`pin.feature`)는 `@javascript`가 없으므로
>   실제 브라우저·Selenium·Java가 **필요 없다**. Behat 기본(`default`) 프로파일로 돈다.
> - **일회용 DB**: Behat 전용 MySQL을 Docker로 잠깐 띄웠다가 끝나면 버린다.
>   (이미 3306에 떠 있는 MySQL은 건드리지 않는다)

---

## 0. 한눈에 보기

```
1) Docker로 MySQL 띄우기            (포트 3307, 일회용)
2) composer로 moodle-plugin-ci 설치  (^4)
3) moodle-plugin-ci install         (Moodle 4.5 클론 + DB 설치 + Behat 환경 초기화)
4) php -S 로 웹서버 띄우기            (별도 터미널, 브라우저 아님)
5) moodle-plugin-ci behat           (헤드리스 실행)
6) 정리                              (웹서버 종료 · Docker 컨테이너 삭제)
```

왜 Moodle **4.5**인가? 이 PC의 PHP가 **8.3 단일**이라, CI 매트릭스 두 조합
(8.3/4.5, 7.4/3.9) 중 PHP 7.4를 따로 깔지 않고 바로 돌릴 수 있는 건 **8.3 + Moodle 4.5
(`MOODLE_405_STABLE`) + moodle-plugin-ci `^4`** 조합이다.

---

## 1. 사전 요건

이 PC에는 아래가 이미 깔려 있다(확인된 버전). 없다면 먼저 설치해야 한다.

| 도구 | 필요 버전 | 이 PC | 용도 |
| --- | --- | --- | --- |
| PHP | 8.3 (mysqli·gd·intl·zip·mbstring·soap·sodium·curl) | 8.3.31 ✅ | Moodle·Behat 실행 |
| Composer | 2.x | 2.9.7 ✅ | moodle-plugin-ci 설치 |
| Docker | 최신 | 29.4.3 ✅ | 일회용 MySQL |
| MySQL 클라이언트 | 8.x | 8.4.9 ✅ | DB 준비 확인용 |
| git | 2.x | 2.47.3 ✅ | Moodle 코어 클론 |
| Node/npm | (있으면) | 16.20.2 ⚠️ | grunt용. **Behat엔 불필요** (5장 참고) |
| 브라우저·chromedriver·Java | — | 없음 ✅ | **헤드리스라 필요 없음** |

추가로 한 번만 챙길 것 두 가지:

```bash
# (1) Moodle 테스트가 사용하는 로케일 생성 (CI의 locale-gen 단계와 동일)
sudo locale-gen en_AU.UTF-8

# (2) PHP CLI 설정에 max_input_vars=5000 이상인지 확인 (Moodle 환경 요건)
php -i | grep max_input_vars
#   값이 5000 미만이면 사용 중인 php.ini 에 max_input_vars=5000 추가
php --ini   # 어떤 php.ini 를 쓰는지 경로 확인
```

---

## 2. 작업 공간 만들기

Moodle 코어 클론과 moodle-plugin-ci 도구는 **용량이 크고** 버려질 것이므로
**이 레포 바깥**의 스크래치 폴더에 둔다. 레포 git 상태를 더럽히지 않기 위해서다.

```bash
# 스크래치 폴더 (원하면 다른 경로로 바꿔도 됨)
export WORKSPACE="$HOME/workspace/_local-behat"
mkdir -p "$WORKSPACE"

# 이후 명령들이 공통으로 쓰는 경로 — 한 번 export 해두면 편하다
export PLUGIN_DIR="$HOME/workspace/demo-moodle-plugin"   # 이 플러그인 레포
export MOODLE_DIR="$WORKSPACE/moodle"                    # Moodle 4.5 가 클론될 곳
```

> `PLUGIN_DIR`·`MOODLE_DIR`는 moodle-plugin-ci가 읽는 환경변수다. 이걸 export 해두면
> 뒤의 `behat` 명령에서 경로를 다시 안 적어도 된다.
> **터미널을 새로 열면 이 export 들을 다시 해줘야 한다.**

---

## 3. Docker로 MySQL 띄우기

CI의 `services: mysql` 블록과 동일한 설정으로, **포트만 3307**로 띄운다
(이미 쓰는 3306과 충돌 피하려고).

```bash
docker run -d --name moodle-behat-mysql \
  -p 3307:3306 \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=true \
  -e MYSQL_CHARACTER_SET_SERVER=utf8mb4 \
  -e MYSQL_COLLATION_SERVER=utf8mb4_unicode_ci \
  mysql:8.0
```

준비될 때까지(보통 10~20초) 기다린다:

```bash
until mysqladmin ping -h 127.0.0.1 -P 3307 -u root --silent 2>/dev/null; do
  echo "MySQL 기동 대기..."; sleep 2
done
echo "MySQL 준비 완료"
```

- 사용자 `root`, **비밀번호 없음**, DB 이름은 다음 단계에서 `moodle`로 만들어진다.
- `mysql:8.0`은 CI가 검증한 조합이다(Moodle 4.5와 호환).

---

## 4. moodle-plugin-ci 설치 (composer)

도구를 스크래치 폴더에 `create-project`로 설치하고, 실행 파일 경로를 PATH에 추가한다.

```bash
composer create-project moodlehq/moodle-plugin-ci "$WORKSPACE/moodle-plugin-ci" ^4

export PATH="$WORKSPACE/moodle-plugin-ci/bin:$WORKSPACE/moodle-plugin-ci/vendor/bin:$PATH"

# 확인
moodle-plugin-ci --version
```

> `^4`는 CI 워크플로의 8.3/4.5 조합이 쓰는 버전과 같다.

---

## 5. Moodle 4.5 설치 (`moodle-plugin-ci install`)

이 한 줄이 가장 무거운 작업이다. **Moodle 4.5 클론 → DB에 설치 → 플러그인 배치 →
Behat/PHPUnit 환경 초기화**까지 한 번에 한다.

```bash
moodle-plugin-ci install \
  --plugin "$PLUGIN_DIR" \
  --moodle "$MOODLE_DIR" \
  --branch MOODLE_405_STABLE \
  --db-type mysqli \
  --db-host 127.0.0.1 \
  --db-port 3307 \
  --db-user root
```

플래그 의미:

| 플래그 | 뜻 |
| --- | --- |
| `--plugin` | 테스트할 플러그인(이 레포). Moodle 트리의 `local/demo`로 복사된다 |
| `--moodle` | Moodle 4.5를 클론할 위치 |
| `--branch` | `MOODLE_405_STABLE` = Moodle 4.5 안정 브랜치 |
| `--db-type` | `mysqli` (CI의 `DB=mysqli`와 동일) |
| `--db-host` / `--db-port` | 3장에서 띄운 Docker MySQL |
| `--db-user` | `root` (비밀번호 없음이 기본값이라 `--db-pass` 생략) |

> ⏱️ **시간·용량**: Moodle 클론 + composer 의존성 설치로 수 분, 수백 MB를 쓴다.
>
> ⚠️ **Node 16 관련**: 이 PC의 Node는 16인데 Moodle 4.5의 grunt는 더 최신 Node를
> 권장한다. install 중 npm/grunt 관련 단계에서 경고나 실패가 날 수 있다.
> **우리는 grunt가 아니라 Behat만 돌리므로** 이 단계가 실패해도 DB 설치와 Behat
> 초기화가 끝났다면 진행에 문제 없다. (자세한 대처는 9장 트러블슈팅 참고)

설치가 끝나면 Moodle 설정에 Behat용 값이 들어가 있다. 다음 단계에서 쓸 **웹서버
주소**를 확인해 둔다:

```bash
grep behat_ "$MOODLE_DIR/config.php"
#   예) $CFG->behat_wwwroot   = 'http://localhost:8000';
#       $CFG->behat_dataroot  = '...';
#       $CFG->behat_prefix    = 'beh_';
```

`behat_wwwroot`의 호스트·포트를 다음 단계의 `php -S`와 **똑같이** 맞춰야 한다
(아래는 기본값인 `localhost:8000` 가정).

---

## 6. PHP 웹서버 띄우기 (별도 터미널)

> 왜 필요한가? `@javascript`가 없는 비-JS Behat도 Moodle 페이지를 **HTTP로 요청**한다.
> 그래서 브라우저는 필요 없어도 **웹서버는 떠 있어야** 한다. (Selenium은 여전히 불필요)

**새 터미널**을 하나 더 열고, 그 창에서 서버를 계속 띄워 둔다(끝날 때까지 켜둠):

```bash
# 새 터미널 — 경로 export 부터 다시
export MOODLE_DIR="$HOME/workspace/_local-behat/moodle"

# 5장에서 확인한 behat_wwwroot 의 포트와 동일하게
php -S localhost:8000 -t "$MOODLE_DIR"
```

이 창은 그대로 두고, 아래 7장은 **원래 터미널**(PATH·env가 설정된)에서 진행한다.

---

## 7. Behat 실행

원래 터미널에서:

```bash
moodle-plugin-ci behat
```

- `--profile`을 주지 않으므로 **기본값 `default`** = 헤드리스 드라이버(브라우저 없음).
- `--start-servers`를 **붙이지 않으므로** moodle-plugin-ci가 Selenium을 띄우지 않는다
  (웹서버는 6장에서 우리가 직접 띄웠다).
- 플러그인·Moodle 경로는 2장에서 export 한 `PLUGIN_DIR`·`MOODLE_DIR`로 자동 인식된다.

성공하면 두 feature의 시나리오가 통과(초록)로 끝난다.

### 일부만 골라 돌리기

```bash
# 특정 시나리오 이름으로
moodle-plugin-ci behat --name "관리자가 메모를 올리면 목록에 보인다"

# 태그로 (기본 태그는 컴포넌트명 @local_demo 이다)
moodle-plugin-ci behat --tags @local_demo
```

> `--feature`로 특정 `.feature` 파일만 돌리고 싶으면, moodle-plugin-ci 대신 Moodle
> 네이티브 러너를 직접 부르는 방법도 있다(9장 참고).

---

## 8. 정리 (teardown)

```bash
# 6장의 php -S 터미널에서 Ctrl+C 로 웹서버 종료

# Docker MySQL 삭제 (데이터까지 같이 사라짐 — 일회용이므로 OK)
docker rm -f moodle-behat-mysql

# 스크래치 폴더 통째로 삭제 (Moodle 클론 + 도구)
rm -rf "$HOME/workspace/_local-behat"
```

---

## 9. 트러블슈팅

**포트가 이미 사용 중 (3307 또는 8000)**
다른 포트로 바꾼다. 3307을 바꾸면 3장 `docker run -p`와 5장 `--db-port`를 같이 바꾸고,
8000을 바꾸면 6장 `php -S`와 5장에서 확인한 `behat_wwwroot`를 같이 맞춘다.
```bash
ss -tlnp | grep -E '3307|8000'   # 무엇이 점유 중인지 확인
```

**MySQL 연결 실패 (install이 DB에 못 붙음)**
컨테이너가 떴는지, ping이 되는지 확인:
```bash
docker ps | grep moodle-behat-mysql
mysqladmin ping -h 127.0.0.1 -P 3307 -u root
```
WSL 환경에서 호스트→컨테이너는 `127.0.0.1`로 접속한다(`localhost` 소켓 아님).

**`behat_wwwroot` 포트 불일치**
`grep behat_ "$MOODLE_DIR/config.php"`로 나온 포트와 `php -S`의 포트가 다르면 Behat이
사이트에 접속하지 못한다. 둘을 똑같이 맞춘다.

**Node 16 / npm·grunt 단계 실패 (5장)**
Behat만 쓸 거라면 이 단계 실패는 무시해도 된다(DB 설치·Behat 초기화가 끝났는지만 확인).
정석으로 맞추려면 nvm으로 Moodle 4.5가 요구하는 Node 버전을 설치한다:
```bash
# 예시 (nvm 설치 후)
nvm install 20 && nvm use 20
```

**로케일 경고 (`en_AU.UTF-8`)**
1장의 `sudo locale-gen en_AU.UTF-8`을 실행했는지 확인. `locale -a | grep en_AU`로 확인.

**`@javascript` 시나리오를 추가했다면**
실제 브라우저가 필요해진다. 이 경우엔 헤드리스로 안 되고, Docker로
`selenium/standalone-chrome`를 띄운 뒤 `moodle-plugin-ci behat --profile chrome
--start-servers` 형태로 가야 한다. (이 문서 범위 밖)

**Moodle 네이티브 Behat 러너를 직접 부르고 싶을 때**
moodle-plugin-ci를 거치지 않고 특정 feature만 돌리는 등 세밀한 제어가 필요하면:
```bash
# 환경 재생성(필요 시)
php "$MOODLE_DIR/admin/tool/behat/cli/init.php"
# 단일 feature 실행
php "$MOODLE_DIR/admin/tool/behat/cli/run.php" \
  --feature="$PLUGIN_DIR/tests/behat/pin.feature"
```

---

## 부록 — GitHub Actions 워크플로 ↔ 로컬 단계 대응

| `.github/workflows/moodle-ci.yml` | 이 문서의 로컬 대응 |
| --- | --- |
| `services: mysql` (3306) | 3장 Docker MySQL (3307) |
| `composer create-project ... moodle-plugin-ci ci ^4` | 4장 |
| `moodle-plugin-ci install --plugin ./plugin` (`DB`, `MOODLE_BRANCH` env) | 5장 (`--branch`/`--db-*` 플래그로 명시) |
| (러너가 자동) 웹서버 | 6장 `php -S` 직접 |
| `moodle-plugin-ci behat --profile chrome` | 7장 `moodle-plugin-ci behat` (헤드리스 `default`) |
| 러너 폐기 | 8장 정리 |

> 정적 검사들(`phpmd`·`validate`·`savepoints`·`mustache`·`grunt`·`phpunit`)도 같은 방식으로
> `moodle-plugin-ci <명령>` 으로 돌릴 수 있다. 이 문서는 요청대로 **Behat**에 집중한다.
