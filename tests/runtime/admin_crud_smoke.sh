#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -lt 1 ]; then
  echo "Usage: admin_crud_smoke.sh <opencart-upload>" >&2
  exit 2
fi

ROOT="$(cd "$1" && pwd)"
HOST="127.0.0.1"
PORT="8080"
BASE="http://${HOST}:${PORT}"
LOG="/tmp/probg-team-admin-http-server.log"
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
    $result = $db->query($argv[1]);
    if ($result === false) { fwrite(STDERR, $db->error . "\nSQL: " . $argv[1] . "\n"); exit(1); }
    if ($result instanceof mysqli_result && ($row = $result->fetch_row())) { echo $row[0]; }
  ' -- "$sql"
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

# Register and refresh the real OCMOD so the Team sidebar entry is also tested.
REFRESH_STATUS="$(request_status refresh GET "${BASE}/admin/index.php?route=marketplace/modification/refresh&user_token=${USER_TOKEN}")"
expect_status refresh "$REFRESH_STATUS" 302

# Install through OpenCart's real module extension route. This invokes Team::install(),
# grants Team permissions, creates schema/settings and ensures a default members instance.
INSTALL_STATUS="$(request_status module-install GET "${BASE}/admin/index.php?route=extension/extension/module/install&extension=probg_team&user_token=${USER_TOKEN}")"
expect_status module-install "$INSTALL_STATUS" 200
contains module-install 'ProBG Team'

if [ "$(db_scalar "SELECT COUNT(*) FROM oc_extension WHERE type='module' AND code='probg_team'")" != "1" ]; then
  fail "ProBG Team was not registered as an installed module"
fi

SETTINGS_STATUS="$(request_status settings-page GET "${BASE}/admin/index.php?route=extension/module/probg_team&user_token=${USER_TOKEN}")"
expect_status settings-page "$SETTINGS_STATUS" 200
contains settings-page 'ProBG Team'
contains settings-page 'menu-probg-team'

CATEGORY_PAGE_STATUS="$(request_status category-page GET "${BASE}/admin/index.php?route=extension/probg_team/category&user_token=${USER_TOKEN}")"
expect_status category-page "$CATEGORY_PAGE_STATUS" 200
contains category-page 'id="form-category"'

MEMBER_PAGE_STATUS="$(request_status member-page GET "${BASE}/admin/index.php?route=extension/probg_team/member&user_token=${USER_TOKEN}")"
expect_status member-page "$MEMBER_PAGE_STATUS" 200
contains member-page 'id="form-member"'

LANGUAGE_ID="$(db_scalar "SELECT language_id FROM oc_language WHERE status=1 ORDER BY sort_order, language_id LIMIT 1")"
[ -n "$LANGUAGE_ID" ] || fail "No active language"

# Save global settings and replace the default instance with an explicit members block plus menu.
SETTINGS_SAVE_STATUS="$(request_status settings-save POST "${BASE}/admin/index.php?route=extension/module/probg_team&user_token=${USER_TOKEN}" \
  --data-urlencode "module_probg_team_description[0][${LANGUAGE_ID}][title]=Admin Runtime Team" \
  --data-urlencode "module_probg_team_description[0][${LANGUAGE_ID}][description]=<p>Admin Runtime description</p>" \
  --data-urlencode "module_probg_team_description[0][${LANGUAGE_ID}][meta_title]=" \
  --data-urlencode "module_probg_team_description[0][${LANGUAGE_ID}][meta_description]=Admin Runtime Team meta" \
  --data-urlencode "module_probg_team_description[0][${LANGUAGE_ID}][meta_keyword]=admin,runtime,team" \
  --data-urlencode "module_probg_team_seo_url[0][${LANGUAGE_ID}]=admin-runtime-team" \
  --data-urlencode 'module_probg_team_status=1' \
  --data-urlencode 'module_probg_team_limit=9' \
  --data-urlencode 'module_probg_team_show_empty_categories=1' \
  --data-urlencode 'module_probg_team_show_telephone=1' \
  --data-urlencode 'module_probg_team_show_city=1' \
  --data-urlencode 'module_probg_team_show_working_hours=1' \
  --data-urlencode 'module_probg_team_show_website=1' \
  --data-urlencode 'module_probg_team_show_social=1' \
  --data-urlencode 'module_probg_team_open_graph_status=1' \
  --data-urlencode 'module_probg_team_schema_status=1' \
  --data-urlencode 'module_probg_team_cache_status=1' \
  --data-urlencode 'module_probg_team_search_status=1' \
  --data-urlencode 'module_probg_team_search_limit=7' \
  --data-urlencode 'module_probg_team_sitemap_status=1' \
  --data-urlencode 'module_probg_team_list_width=320' \
  --data-urlencode 'module_probg_team_list_height=320' \
  --data-urlencode 'module_probg_team_member_width=720' \
  --data-urlencode 'module_probg_team_member_height=720' \
  --data-urlencode 'module_probg_team_gallery_width=220' \
  --data-urlencode 'module_probg_team_gallery_height=220' \
  --data-urlencode 'probg_team_blocks[0][module_id]=0' \
  --data-urlencode 'probg_team_blocks[0][name]=Admin Runtime Members' \
  --data-urlencode 'probg_team_blocks[0][probg_team_type]=members' \
  --data-urlencode "probg_team_blocks[0][title][${LANGUAGE_ID}]=Runtime Members Block" \
  --data-urlencode 'probg_team_blocks[0][team_category_id]=0' \
  --data-urlencode 'probg_team_blocks[0][limit]=5' \
  --data-urlencode 'probg_team_blocks[0][columns]=3' \
  --data-urlencode 'probg_team_blocks[0][sort]=name' \
  --data-urlencode 'probg_team_blocks[0][show_category]=1' \
  --data-urlencode 'probg_team_blocks[0][show_city]=1' \
  --data-urlencode 'probg_team_blocks[0][show_description]=1' \
  --data-urlencode 'probg_team_blocks[0][status]=1' \
  --data-urlencode 'probg_team_menus[0][module_id]=0' \
  --data-urlencode 'probg_team_menus[0][name]=Admin Runtime Menu' \
  --data-urlencode 'probg_team_menus[0][probg_team_type]=menu' \
  --data-urlencode "probg_team_menus[0][title][${LANGUAGE_ID}]=Runtime Team Menu" \
  --data-urlencode 'probg_team_menus[0][team_category_id]=0' \
  --data-urlencode 'probg_team_menus[0][limit]=8' \
  --data-urlencode 'probg_team_menus[0][status]=1')"
expect_status settings-save "$SETTINGS_SAVE_STATUS" 302

SETTINGS_VERIFY_STATUS="$(request_status settings-verify GET "${BASE}/admin/index.php?route=extension/module/probg_team&user_token=${USER_TOKEN}")"
expect_status settings-verify "$SETTINGS_VERIFY_STATUS" 200
contains settings-verify 'Admin Runtime Team'
contains settings-verify 'Admin Runtime Members'
contains settings-verify 'Admin Runtime Menu'

if [ "$(db_scalar "SELECT value FROM oc_setting WHERE store_id=0 AND code='module_probg_team' AND \`key\`='module_probg_team_limit' LIMIT 1")" != "9" ]; then
  fail "Global settings POST was not persisted"
fi
if [ "$(db_scalar "SELECT COUNT(*) FROM oc_module WHERE code='probg_team'")" != "2" ]; then
  fail "Expected exactly one members block and one menu instance"
fi
if [ "$(db_scalar "SELECT COUNT(*) FROM oc_module WHERE code='probg_team' AND setting LIKE '%\\\"probg_team_type\\\":\\\"menu\\\"%'")" != "1" ]; then
  fail "Menu module instance was not persisted"
fi

# Diagnostics repair and manual cache rotation endpoints.
REPAIR_STATUS="$(request_status repair GET "${BASE}/admin/index.php?route=extension/module/probg_team/repair&user_token=${USER_TOKEN}")"
expect_status repair "$REPAIR_STATUS" 302
CLEAR_STATUS="$(request_status clear-cache GET "${BASE}/admin/index.php?route=extension/module/probg_team/clearCache&user_token=${USER_TOKEN}")"
expect_status clear-cache "$CLEAR_STATUS" 302

# Category create through the real administration controller.
CATEGORY_ADD_STATUS="$(request_status category-add POST "${BASE}/admin/index.php?route=extension/probg_team/category/add&user_token=${USER_TOKEN}" \
  --data-urlencode "category_description[${LANGUAGE_ID}][name]=Admin Runtime Category" \
  --data-urlencode "category_description[${LANGUAGE_ID}][description]=<p>Category created through admin HTTP</p>" \
  --data-urlencode "category_description[${LANGUAGE_ID}][meta_title]=" \
  --data-urlencode "category_description[${LANGUAGE_ID}][meta_description]=Admin category meta" \
  --data-urlencode "category_description[${LANGUAGE_ID}][meta_keyword]=admin,category" \
  --data-urlencode "category_seo_url[0][${LANGUAGE_ID}]=admin-runtime-category" \
  --data-urlencode 'category_store[]=0' \
  --data-urlencode 'category_layout[0]=0' \
  --data-urlencode 'sort_order=4' \
  --data-urlencode 'status=1')"
expect_status category-add "$CATEGORY_ADD_STATUS" 302

CATEGORY_ID="$(db_scalar "SELECT c.team_category_id FROM oc_team_category c JOIN oc_team_category_description cd ON cd.team_category_id=c.team_category_id WHERE cd.name='Admin Runtime Category' AND cd.language_id=${LANGUAGE_ID} ORDER BY c.team_category_id DESC LIMIT 1")"
[ -n "$CATEGORY_ID" ] || fail "Category was not created"

CATEGORY_FILTER_STATUS="$(request_status category-filter GET "${BASE}/admin/index.php?route=extension/probg_team/category&user_token=${USER_TOKEN}&filter_name=Admin%20Runtime%20Category")"
expect_status category-filter "$CATEGORY_FILTER_STATUS" 200
contains category-filter 'Admin Runtime Category'

CATEGORY_EDIT_STATUS="$(request_status category-edit POST "${BASE}/admin/index.php?route=extension/probg_team/category/edit&user_token=${USER_TOKEN}&team_category_id=${CATEGORY_ID}" \
  --data-urlencode "category_description[${LANGUAGE_ID}][name]=Admin Runtime Category Updated" \
  --data-urlencode "category_description[${LANGUAGE_ID}][description]=<p>Updated category through admin HTTP</p>" \
  --data-urlencode "category_description[${LANGUAGE_ID}][meta_title]=" \
  --data-urlencode "category_description[${LANGUAGE_ID}][meta_description]=Updated category meta" \
  --data-urlencode "category_description[${LANGUAGE_ID}][meta_keyword]=updated,category" \
  --data-urlencode "category_seo_url[0][${LANGUAGE_ID}]=admin-runtime-category-updated" \
  --data-urlencode 'category_store[]=0' \
  --data-urlencode 'category_layout[0]=0' \
  --data-urlencode 'sort_order=6' \
  --data-urlencode 'status=1')"
expect_status category-edit "$CATEGORY_EDIT_STATUS" 302

if [ "$(db_scalar "SELECT name FROM oc_team_category_description WHERE team_category_id=${CATEGORY_ID} AND language_id=${LANGUAGE_ID}")" != "Admin Runtime Category Updated" ]; then
  fail "Category edit was not persisted"
fi

# Member create through the real administration controller.
MEMBER_ADD_STATUS="$(request_status member-add POST "${BASE}/admin/index.php?route=extension/probg_team/member/add&user_token=${USER_TOKEN}" \
  --data-urlencode "member_description[${LANGUAGE_ID}][name]=Admin Runtime Member" \
  --data-urlencode "member_description[${LANGUAGE_ID}][short_description]=<p>Admin HTTP short description</p>" \
  --data-urlencode "member_description[${LANGUAGE_ID}][description]=<p>Admin HTTP full description</p>" \
  --data-urlencode "member_description[${LANGUAGE_ID}][telephone]=+359 2 555 0101" \
  --data-urlencode "member_description[${LANGUAGE_ID}][city]=Sofia" \
  --data-urlencode "member_description[${LANGUAGE_ID}][working_hours]=Mon-Fri 10:00-19:00" \
  --data-urlencode "member_description[${LANGUAGE_ID}][website]=https://example.com/admin-runtime" \
  --data-urlencode "member_description[${LANGUAGE_ID}][facebook]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][instagram]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][youtube]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][linkedin]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][meta_title]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][meta_description]=Admin member meta" \
  --data-urlencode "member_description[${LANGUAGE_ID}][meta_keyword]=admin,member" \
  --data-urlencode "member_seo_url[0][${LANGUAGE_ID}]=admin-runtime-member" \
  --data-urlencode "team_category_id=${CATEGORY_ID}" \
  --data-urlencode 'member_store[]=0' \
  --data-urlencode 'image=' \
  --data-urlencode 'sort_order=3' \
  --data-urlencode 'status=1')"
expect_status member-add "$MEMBER_ADD_STATUS" 302

MEMBER_ID="$(db_scalar "SELECT m.team_member_id FROM oc_team_member m JOIN oc_team_member_description md ON md.team_member_id=m.team_member_id WHERE md.name='Admin Runtime Member' AND md.language_id=${LANGUAGE_ID} ORDER BY m.team_member_id DESC LIMIT 1")"
[ -n "$MEMBER_ID" ] || fail "Member was not created"

MEMBER_FILTER_STATUS="$(request_status member-filter GET "${BASE}/admin/index.php?route=extension/probg_team/member&user_token=${USER_TOKEN}&filter_name=Admin%20Runtime%20Member")"
expect_status member-filter "$MEMBER_FILTER_STATUS" 200
contains member-filter 'Admin Runtime Member'
contains member-filter 'Sofia'

MEMBER_EDIT_STATUS="$(request_status member-edit POST "${BASE}/admin/index.php?route=extension/probg_team/member/edit&user_token=${USER_TOKEN}&team_member_id=${MEMBER_ID}" \
  --data-urlencode "member_description[${LANGUAGE_ID}][name]=Admin Runtime Member Updated" \
  --data-urlencode "member_description[${LANGUAGE_ID}][short_description]=<p>Updated short description</p>" \
  --data-urlencode "member_description[${LANGUAGE_ID}][description]=<p>Updated full description</p>" \
  --data-urlencode "member_description[${LANGUAGE_ID}][telephone]=+359 2 555 0202" \
  --data-urlencode "member_description[${LANGUAGE_ID}][city]=Plovdiv" \
  --data-urlencode "member_description[${LANGUAGE_ID}][working_hours]=Tue-Sat 09:30-18:30" \
  --data-urlencode "member_description[${LANGUAGE_ID}][website]=https://example.com/admin-runtime-updated" \
  --data-urlencode "member_description[${LANGUAGE_ID}][facebook]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][instagram]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][youtube]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][linkedin]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][meta_title]=" \
  --data-urlencode "member_description[${LANGUAGE_ID}][meta_description]=Updated member meta" \
  --data-urlencode "member_description[${LANGUAGE_ID}][meta_keyword]=updated,member" \
  --data-urlencode "member_seo_url[0][${LANGUAGE_ID}]=admin-runtime-member-updated" \
  --data-urlencode "team_category_id=${CATEGORY_ID}" \
  --data-urlencode 'member_store[]=0' \
  --data-urlencode 'image=' \
  --data-urlencode 'sort_order=7' \
  --data-urlencode 'status=1')"
expect_status member-edit "$MEMBER_EDIT_STATUS" 302

if [ "$(db_scalar "SELECT city FROM oc_team_member_description WHERE team_member_id=${MEMBER_ID} AND language_id=${LANGUAGE_ID}")" != "Plovdiv" ]; then
  fail "Member edit was not persisted"
fi

# Integrity: category deletion must be blocked while it still owns a member.
CATEGORY_DELETE_BLOCKED_STATUS="$(request_status category-delete-blocked POST "${BASE}/admin/index.php?route=extension/probg_team/category/delete&user_token=${USER_TOKEN}" \
  --data-urlencode "selected[]=${CATEGORY_ID}")"
expect_status category-delete-blocked "$CATEGORY_DELETE_BLOCKED_STATUS" 302
if [ "$(db_scalar "SELECT COUNT(*) FROM oc_team_category WHERE team_category_id=${CATEGORY_ID}")" != "1" ]; then
  fail "Category with members was incorrectly deleted"
fi

MEMBER_DELETE_STATUS="$(request_status member-delete POST "${BASE}/admin/index.php?route=extension/probg_team/member/delete&user_token=${USER_TOKEN}" \
  --data-urlencode "selected[]=${MEMBER_ID}")"
expect_status member-delete "$MEMBER_DELETE_STATUS" 302
if [ "$(db_scalar "SELECT COUNT(*) FROM oc_team_member WHERE team_member_id=${MEMBER_ID}")" != "0" ]; then
  fail "Member deletion did not persist"
fi

CATEGORY_DELETE_STATUS="$(request_status category-delete POST "${BASE}/admin/index.php?route=extension/probg_team/category/delete&user_token=${USER_TOKEN}" \
  --data-urlencode "selected[]=${CATEGORY_ID}")"
expect_status category-delete "$CATEGORY_DELETE_STATUS" 302
if [ "$(db_scalar "SELECT COUNT(*) FROM oc_team_category WHERE team_category_id=${CATEGORY_ID}")" != "0" ]; then
  fail "Category deletion did not persist after member removal"
fi

if grep -Eiq 'Fatal error|Uncaught (Error|Exception)|Parse error' "$LOG"; then
  fail "Fatal PHP output detected during admin CRUD flow"
fi

echo "Admin install, permissions, settings, instances, diagnostics and CRUD smoke test OK"
