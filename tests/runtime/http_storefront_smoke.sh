#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 3 ]; then
  echo "Usage: http_storefront_smoke.sh <opencart-upload> <category-id> <member-id>" >&2
  exit 2
fi

ROOT="$(cd "$1" && pwd)"
CATEGORY_ID="$2"
MEMBER_ID="$3"
HOST="127.0.0.1"
PORT="8080"
BASE="http://${HOST}:${PORT}"
LOG="/tmp/probg-team-http-server.log"
TMP="$(mktemp -d)"

cleanup() {
  if [ -n "${SERVER_PID:-}" ]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TMP"
}
trap cleanup EXIT

php -S "${HOST}:${PORT}" -t "$ROOT" >"$LOG" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 30); do
  if curl -fsS "${BASE}/index.php?route=common/home" >/dev/null 2>&1; then
    break
  fi
  sleep 0.5
done

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

request home "${BASE}/index.php?route=common/home" 200
contains home "OpenCart"

request section "${BASE}/index.php?route=extension/probg_team/team&probg_team_section=1" 200
contains section "Runtime Team"
contains section "Runtime Category"
contains section 'application/ld+json'

request category "${BASE}/index.php?route=extension/probg_team/category&probg_team_category_id=${CATEGORY_ID}" 200
contains category "Runtime Category"
contains category "Runtime Member"
contains category "Sofia"

request member "${BASE}/index.php?route=extension/probg_team/member&probg_team_category_id=${CATEGORY_ID}&probg_team_member_id=${MEMBER_ID}" 200
contains member "Runtime Member"
contains member "Runtime Category"
contains member "Mon-Fri 09:00-18:00"
contains member "ProfilePage"

request sitemap "${BASE}/index.php?route=extension/feed/probg_team_sitemap" 200
contains sitemap '<urlset'
contains sitemap 'extension/probg_team/team'
contains sitemap 'extension/probg_team/category'
contains sitemap 'extension/probg_team/member'

request missing-category "${BASE}/index.php?route=extension/probg_team/category&probg_team_category_id=999999" 404
request wrong-hierarchy "${BASE}/index.php?route=extension/probg_team/member&probg_team_category_id=999999&probg_team_member_id=${MEMBER_ID}" 404

if grep -Eiq 'Fatal error|Uncaught (Error|Exception)|Parse error' "$LOG"; then
  echo "ERROR: fatal PHP output detected in server log" >&2
  cat "$LOG" >&2
  exit 1
fi

echo "HTTP storefront smoke test OK"
