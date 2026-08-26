#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 3 ]; then
  echo "Usage: ocmod_http_smoke.sh <opencart-upload> <category-id> <member-id>" >&2
  exit 2
fi

ROOT="$(cd "$1" && pwd)"
CATEGORY_ID="$2"
MEMBER_ID="$3"
HOST="127.0.0.1"
PORT="8080"
BASE="http://${HOST}:${PORT}"
LOG="/tmp/probg-team-ocmod-http-server.log"
TMP="$(mktemp -d)"
COOKIE="$TMP/admin.cookies"
ROUTER="$(cd "$(dirname "$0")" && pwd)/php_router.php"

cleanup() {
  if [ -n "${SERVER_PID:-}" ]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TMP"
}
trap cleanup EXIT

php -S "${HOST}:${PORT}" -t "$ROOT" "$ROUTER" >"$LOG" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 40); do
  if curl -fsS "${BASE}/index.php?route=common/home" >/dev/null 2>&1; then
    break
  fi
  sleep 0.5
done

LOGIN_HEADERS="$TMP/login.headers"
LOGIN_STATUS="$(curl -sS -c "$COOKIE" -b "$COOKIE" -D "$LOGIN_HEADERS" -o "$TMP/login.body" -w '%{http_code}' \
  -X POST \
  --data-urlencode 'username=admin' \
  --data-urlencode 'password=admin' \
  "${BASE}/admin/index.php?route=common/login")"

if [ "$LOGIN_STATUS" != "302" ]; then
  echo "ERROR: admin login returned HTTP ${LOGIN_STATUS}" >&2
  cat "$LOGIN_HEADERS" >&2 || true
  cat "$TMP/login.body" >&2 || true
  cat "$LOG" >&2 || true
  exit 1
fi

LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/ {sub(/\r$/, ""); print substr($0, 11)}' "$LOGIN_HEADERS" | tail -n1)"
USER_TOKEN="$(printf '%s' "$LOCATION" | sed -n 's/.*[?&]user_token=\([^&[:space:]]*\).*/\1/p')"

if [ -z "$USER_TOKEN" ]; then
  echo "ERROR: could not extract user_token from admin login redirect: ${LOCATION}" >&2
  exit 1
fi

REFRESH_HEADERS="$TMP/refresh.headers"
REFRESH_STATUS="$(curl -sS -c "$COOKIE" -b "$COOKIE" -D "$REFRESH_HEADERS" -o "$TMP/refresh.body" -w '%{http_code}' \
  "${BASE}/admin/index.php?route=marketplace/modification/refresh&user_token=${USER_TOKEN}")"

if [ "$REFRESH_STATUS" != "302" ]; then
  echo "ERROR: Modifications refresh returned HTTP ${REFRESH_STATUS}" >&2
  cat "$REFRESH_HEADERS" >&2 || true
  cat "$TMP/refresh.body" >&2 || true
  cat "$LOG" >&2 || true
  exit 1
fi

MOD_ROOT="$ROOT/system/storage/modification"
check_modified() {
  local file="$1"
  local marker="$2"
  local path="$MOD_ROOT/$file"
  if [ ! -f "$path" ]; then
    echo "ERROR: expected modified file is missing: $file" >&2
    find "$MOD_ROOT" -type f -maxdepth 8 -print >&2 || true
    exit 1
  fi
  if ! grep -Fq "$marker" "$path"; then
    echo "ERROR: modified file does not contain marker '$marker': $file" >&2
    exit 1
  fi
}

check_modified 'catalog/controller/startup/seo_url.php' 'probg_team_section'
check_modified 'catalog/model/design/layout.php' 'team_category_to_layout'
check_modified 'catalog/controller/common/header.php' 'probg_team_og_title'
check_modified 'catalog/view/theme/default/template/common/header.twig' 'twitter:card'
check_modified 'catalog/controller/product/search.php' 'probg_team_search_results'
check_modified 'catalog/view/theme/default/template/product/search.twig' 'probg-team-search-results'
check_modified 'catalog/controller/extension/feed/google_sitemap.php' 'getSitemapMembers'
check_modified 'admin/controller/common/column_left.php' 'menu-probg-team'

OCMOD_LOG="$ROOT/system/storage/logs/ocmod.log"
if [ -f "$OCMOD_LOG" ] && grep -Eiq 'NOT FOUND|ABORT|ERROR' "$OCMOD_LOG"; then
  echo "ERROR: OCMOD log contains a failure marker" >&2
  cat "$OCMOD_LOG" >&2
  exit 1
fi

request() {
  local name="$1"
  local url="$2"
  local expected_status="$3"
  local body="$TMP/${name}.body"
  local headers="$TMP/${name}.headers"
  local status
  status="$(curl -sS -D "$headers" -o "$body" -w '%{http_code}' "$url")"
  if [ "$status" != "$expected_status" ]; then
    echo "ERROR: ${name} returned HTTP ${status}, expected ${expected_status}" >&2
    cat "$headers" >&2 || true
    cat "$body" >&2 || true
    echo "--- PHP server log ---" >&2
    cat "$LOG" >&2 || true
    exit 1
  fi
}

contains() {
  local name="$1"
  local needle="$2"
  if ! grep -Fq "$needle" "$TMP/${name}.body"; then
    echo "ERROR: ${name} response does not contain: ${needle}" >&2
    cat "$TMP/${name}.body" >&2 || true
    echo "--- PHP server log ---" >&2
    cat "$LOG" >&2 || true
    exit 1
  fi
}

location_contains() {
  local name="$1"
  local needle="$2"
  if ! grep -Fiq "Location: ${needle}" "$TMP/${name}.headers"; then
    echo "ERROR: ${name} redirect does not point to ${needle}" >&2
    cat "$TMP/${name}.headers" >&2 || true
    exit 1
  fi
}

MEMBER_KEYWORD="${MEMBER_ID}-runtime-member"

request seo-section "${BASE}/runtime-team" 200
contains seo-section 'Runtime Team'

request seo-category "${BASE}/runtime-team/runtime-category" 200
contains seo-category 'Runtime Member'

request seo-member "${BASE}/runtime-team/runtime-category/${MEMBER_KEYWORD}" 200
contains seo-member 'Runtime Member'
contains seo-member '<meta property="og:title" content="Runtime Member"'
contains seo-member 'twitter:card'

request canonical-member "${BASE}/index.php?route=extension/probg_team/member&probg_team_category_id=${CATEGORY_ID}&probg_team_member_id=${MEMBER_ID}" 301
location_contains canonical-member "${BASE}/runtime-team/runtime-category/${MEMBER_KEYWORD}"

request search "${BASE}/index.php?route=product/search&search=Runtime" 200
contains search 'Runtime Member'
contains search 'probg-team-search-results'

request google-sitemap "${BASE}/index.php?route=extension/feed/google_sitemap" 200
contains google-sitemap '<urlset'
contains google-sitemap "${BASE}/runtime-team"
contains google-sitemap "${BASE}/runtime-team/runtime-category"
contains google-sitemap "${BASE}/runtime-team/runtime-category/${MEMBER_KEYWORD}"

if grep -Eiq 'Fatal error|Uncaught (Error|Exception)|Parse error' "$LOG"; then
  echo "ERROR: fatal PHP output detected in server log" >&2
  cat "$LOG" >&2
  exit 1
fi

echo "OCMOD refresh and integration HTTP smoke test OK"
