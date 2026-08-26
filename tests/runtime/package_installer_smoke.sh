#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 2 ]; then
  echo "Usage: package_installer_smoke.sh <opencart-upload> <probg-team.ocmod.zip>" >&2
  exit 2
fi

ROOT="$(cd "$1" && pwd)"
PACKAGE="$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"
PACKAGE_NAME="$(basename "$PACKAGE")"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
HOST="127.0.0.1"
PORT="8080"
BASE="http://${HOST}:${PORT}"
LOG="/tmp/probg-team-package-installer-server.log"
TMP="$(mktemp -d)"
COOKIE="$TMP/admin.cookies"
ROUTER="$SCRIPT_DIR/php_router.php"

cleanup() {
  if [ -n "${SERVER_PID:-}" ]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TMP"
}
trap cleanup EXIT

fail() {
  echo "ERROR: $*" >&2
  echo "--- PHP server log ---" >&2
  cat "$LOG" >&2 || true
  exit 1
}

db_scalar() {
  local sql="$1"
  php -r '
    $db = new mysqli("127.0.0.1", "root", "root", "opencart", 3306);
    if ($db->connect_errno) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
    $db->set_charset("utf8");
    $result = $db->query($argv[1]);
    if ($result === false) { fwrite(STDERR, $db->error . "\nSQL: " . $argv[1] . "\n"); exit(1); }
    if ($result instanceof mysqli_result && ($row = $result->fetch_row())) { echo $row[0]; }
  ' -- "$sql"
}

json_field() {
  local file="$1"
  local key="$2"
  php -r '
    $json = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($json)) { fwrite(STDERR, "Invalid JSON in " . $argv[1] . "\n"); exit(1); }
    if (array_key_exists($argv[2], $json) && !is_array($json[$argv[2]])) { echo $json[$argv[2]]; }
  ' -- "$file" "$key"
}

request_status() {
  local name="$1"
  local method="$2"
  local url="$3"
  shift 3
  local body="$TMP/${name}.body"
  local headers="$TMP/${name}.headers"
  local status
  status="$(curl -sS -c "$COOKIE" -b "$COOKIE" -D "$headers" -o "$body" -w '%{http_code}' -X "$method" "$@" "$url")"
  printf '%s' "$status"
}

expect_status() {
  local name="$1"
  local actual="$2"
  local expected="$3"
  if [ "$actual" != "$expected" ]; then
    echo "${name}: expected HTTP ${expected}, got ${actual}" >&2
    cat "$TMP/${name}.headers" >&2 || true
    cat "$TMP/${name}.body" >&2 || true
    fail "$name failed"
  fi
}

contains() {
  local name="$1"
  local needle="$2"
  if ! grep -Fq "$needle" "$TMP/${name}.body"; then
    echo "Response ${name} does not contain: ${needle}" >&2
    cat "$TMP/${name}.body" >&2 || true
    fail "$name content assertion failed"
  fi
}

[ -f "$PACKAGE" ] || fail "Installation package not found: $PACKAGE"
[ "$PACKAGE_NAME" = "probg-team-1.0.02.ocmod.zip" ] || fail "Unexpected package filename: $PACKAGE_NAME"
[ ! -e "$ROOT/admin/controller/extension/module/probg_team.php" ] || fail "Team files are already present before Extensions Installer upload"

php -S "${HOST}:${PORT}" -t "$ROOT" "$ROUTER" >"$LOG" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 40); do
  if curl -fsS "${BASE}/index.php?route=common/home" >/dev/null 2>&1; then
    break
  fi
  sleep 0.5
done

LOGIN_STATUS="$(request_status login POST "${BASE}/admin/index.php?route=common/login" \
  --data-urlencode 'username=admin' \
  --data-urlencode 'password=admin')"
expect_status login "$LOGIN_STATUS" 302

LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {sub(/\r$/, ""); print substr($0, 11)}' "$TMP/login.headers" | tail -n1)"
USER_TOKEN="$(printf '%s' "$LOCATION" | sed -n 's/.*[?&]user_token=\([^&[:space:]]*\).*/\1/p')"
[ -n "$USER_TOKEN" ] || fail "Could not extract user_token"

INSTALLER_PAGE_STATUS="$(request_status installer-page GET "${BASE}/admin/index.php?route=marketplace/installer&user_token=${USER_TOKEN}")"
expect_status installer-page "$INSTALLER_PAGE_STATUS" 200

UPLOAD_STATUS="$(request_status installer-upload POST "${BASE}/admin/index.php?route=marketplace/installer/upload&user_token=${USER_TOKEN}" \
  -F "file=@${PACKAGE};type=application/zip")"
expect_status installer-upload "$UPLOAD_STATUS" 200

UPLOAD_ERROR="$(json_field "$TMP/installer-upload.body" error)"
[ -z "$UPLOAD_ERROR" ] || fail "Extensions Installer upload failed: $UPLOAD_ERROR"
NEXT="$(json_field "$TMP/installer-upload.body" next)"
[ -n "$NEXT" ] || fail "Extensions Installer upload did not return a next step"

for STEP in $(seq 1 12); do
  STEP_NAME="installer-step-${STEP}"
  STEP_STATUS="$(request_status "$STEP_NAME" GET "$NEXT")"
  expect_status "$STEP_NAME" "$STEP_STATUS" 200

  STEP_ERROR="$(json_field "$TMP/${STEP_NAME}.body" error)"
  [ -z "$STEP_ERROR" ] || fail "Extensions Installer step ${STEP} failed: $STEP_ERROR"

  NEXT="$(json_field "$TMP/${STEP_NAME}.body" next)"
  if [ -z "$NEXT" ]; then
    break
  fi

done

[ -z "$NEXT" ] || fail "Extensions Installer did not finish within the expected step count"

INSTALL_ID="$(db_scalar "SELECT extension_install_id FROM oc_extension_install WHERE filename='${PACKAGE_NAME}' ORDER BY extension_install_id DESC LIMIT 1")"
[ -n "$INSTALL_ID" ] || fail "Extensions Installer history row was not created"

PATH_COUNT="$(db_scalar "SELECT COUNT(*) FROM oc_extension_path WHERE extension_install_id=${INSTALL_ID}")"
[ "${PATH_COUNT:-0}" -gt 10 ] || fail "Extensions Installer recorded too few installed paths: ${PATH_COUNT:-0}"

MOD_INSTALL_ID="$(db_scalar "SELECT extension_install_id FROM oc_modification WHERE code='probg_team' ORDER BY modification_id DESC LIMIT 1")"
[ "$MOD_INSTALL_ID" = "$INSTALL_ID" ] || fail "Installed OCMOD is not linked to Extensions Installer history"

MOD_VERSION="$(db_scalar "SELECT version FROM oc_modification WHERE code='probg_team' ORDER BY modification_id DESC LIMIT 1")"
[ "$MOD_VERSION" = "1.0.2" ] || fail "Installed OCMOD version is ${MOD_VERSION:-missing}, expected 1.0.2"

HISTORY_STATUS="$(request_status installer-history GET "${BASE}/admin/index.php?route=marketplace/installer/history&user_token=${USER_TOKEN}")"
expect_status installer-history "$HISTORY_STATUS" 200
contains installer-history "$PACKAGE_NAME"

# The real installer, not a repository overlay, must have copied every packaged upload file byte-for-byte.
while IFS= read -r -d '' SOURCE_FILE; do
  RELATIVE="${SOURCE_FILE#${REPO_ROOT}/upload/}"
  TARGET_FILE="$ROOT/$RELATIVE"
  [ -f "$TARGET_FILE" ] || fail "Installed package is missing file: $RELATIVE"
  cmp -s "$SOURCE_FILE" "$TARGET_FILE" || fail "Installed file differs from packaged source: $RELATIVE"
done < <(find "$REPO_ROOT/upload" -type f -print0)

REFRESH_STATUS="$(request_status refresh GET "${BASE}/admin/index.php?route=marketplace/modification/refresh&user_token=${USER_TOKEN}")"
expect_status refresh "$REFRESH_STATUS" 302

MODIFIED_SEO="$ROOT/system/storage/modification/catalog/controller/startup/seo_url.php"
[ -f "$MODIFIED_SEO" ] || fail "OCMOD refresh did not generate the modified SEO controller"
grep -Fq 'probg_team_section' "$MODIFIED_SEO" || fail "Generated SEO controller does not contain ProBG Team integration"

MODULE_INSTALL_STATUS="$(request_status module-install GET "${BASE}/admin/index.php?route=extension/extension/module/install&extension=probg_team&user_token=${USER_TOKEN}")"
expect_status module-install "$MODULE_INSTALL_STATUS" 200
contains module-install 'ProBG Team'

if [ "$(db_scalar "SELECT COUNT(*) FROM oc_extension WHERE type='module' AND code='probg_team'")" != "1" ]; then
  fail "ProBG Team was not registered as an installed module"
fi
if [ "$(db_scalar "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='opencart' AND table_name='oc_team_member'")" != "1" ]; then
  fail "Team schema was not created by the real module installer"
fi

SETTINGS_STATUS="$(request_status settings-page GET "${BASE}/admin/index.php?route=extension/module/probg_team&user_token=${USER_TOKEN}")"
expect_status settings-page "$SETTINGS_STATUS" 200
contains settings-page 'ProBG Team'
contains settings-page 'menu-probg-team'

FIXTURE_OUTPUT="$TMP/fixture.env"
php "$SCRIPT_DIR/prepare_http_fixture.php" \
  "$ROOT" 127.0.0.1 root root opencart 3306 "${BASE}/" >"$FIXTURE_OUTPUT"
CATEGORY_ID="$(sed -n 's/^CATEGORY_ID=//p' "$FIXTURE_OUTPUT")"
MEMBER_ID="$(sed -n 's/^MEMBER_ID=//p' "$FIXTURE_OUTPUT")"
[ -n "$CATEGORY_ID" ] || fail "Fixture category was not created"
[ -n "$MEMBER_ID" ] || fail "Fixture member was not created"

TEAM_STATUS="$(request_status team-page GET "${BASE}/index.php?route=extension/probg_team/team")"
expect_status team-page "$TEAM_STATUS" 200
contains team-page 'Runtime Team'
contains team-page 'Runtime Category'

CATEGORY_STATUS="$(request_status category-page GET "${BASE}/index.php?route=extension/probg_team/category&probg_team_category_id=${CATEGORY_ID}")"
expect_status category-page "$CATEGORY_STATUS" 200
contains category-page 'Runtime Member'

MEMBER_STATUS="$(request_status member-page GET "${BASE}/index.php?route=extension/probg_team/member&probg_team_category_id=${CATEGORY_ID}&probg_team_member_id=${MEMBER_ID}")"
expect_status member-page "$MEMBER_STATUS" 200
contains member-page 'Runtime Member'
contains member-page 'ProfilePage'

if grep -Eqi 'PHP Fatal error|Uncaught (Exception|Error)|Parse error' "$LOG"; then
  fail "Fatal PHP error detected during package installer runtime"
fi

echo "Package installer E2E OK: ${PACKAGE_NAME}, extension_install_id=${INSTALL_ID}, paths=${PATH_COUNT}"
