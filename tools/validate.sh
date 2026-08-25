#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

for command in php python3 node unzip sha256sum; do
  command -v "$command" >/dev/null 2>&1 || fail "Required command is missing: $command"
done

[ -f install.xml ] || fail "install.xml is missing"
[ -d upload ] || fail "upload/ is missing"

VERSION="$(python3 - <<'PY'
import xml.etree.ElementTree as ET
root = ET.parse('install.xml').getroot()
version = (root.findtext('version') or '').strip()
if not version:
    raise SystemExit('Missing <version> in install.xml')
print(version)
PY
)"

echo "Validating ProBG Team ${VERSION}"

python3 - <<'PY'
import xml.etree.ElementTree as ET
ET.parse('install.xml')
print('install.xml: OK')
PY

for file in \
  upload/admin/controller/extension/module/probg_team.php \
  upload/admin/model/extension/module/probg_team.php; do
  [ -f "$file" ] || fail "Versioned file is missing: $file"
  grep -Fq "const VERSION = '${VERSION}';" "$file" || fail "Version mismatch in $file"
done

PHP_COUNT=0
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  PHP_COUNT=$((PHP_COUNT + 1))
done < <(find upload -type f -name '*.php' -print0 | sort -z)
echo "PHP syntax: ${PHP_COUNT} files OK"

JS_COUNT=0
while IFS= read -r -d '' file; do
  node --check "$file" >/dev/null
  JS_COUNT=$((JS_COUNT + 1))
done < <(find upload -type f -name '*.js' -print0 | sort -z)
echo "JavaScript syntax: ${JS_COUNT} files OK"

if find . -maxdepth 1 -type f -name '*.ocmod.zip' -print -quit | grep -q .; then
  fail "Installation .ocmod.zip files must be stored only in dist/"
fi

validate_package() {
  local package="$1"
  echo "Checking package: $package"
  unzip -t "$package" >/dev/null

  python3 - "$package" <<'PY'
import sys
import zipfile

path = sys.argv[1]
with zipfile.ZipFile(path) as archive:
    names = archive.namelist()

if not names:
    raise SystemExit('Package is empty')

files = [name for name in names if not name.endswith('/')]
if files.count('install.xml') != 1:
    raise SystemExit('Package must contain install.xml exactly once at archive root')
if not any(name.startswith('upload/') for name in files):
    raise SystemExit('Package does not contain upload/ files')

for name in names:
    normalized = name.replace('\\', '/')
    if normalized.startswith('/') or normalized.startswith('../') or '/..' in normalized:
        raise SystemExit('Unsafe archive path: ' + name)
    top = normalized.split('/', 1)[0]
    if top not in ('install.xml', 'upload'):
        raise SystemExit('Unexpected top-level package path: ' + name)

print('Package layout: OK')
PY
}

shopt -s nullglob
PACKAGES=(dist/*.ocmod.zip)
for package in "${PACKAGES[@]}"; do
  validate_package "$package"
done

if [ -f dist/SHA256SUMS ]; then
  [ "${#PACKAGES[@]}" -gt 0 ] || fail "dist/SHA256SUMS exists but no dist package is present"
  (cd dist && sha256sum -c SHA256SUMS)
fi

echo "Validation complete: ProBG Team ${VERSION}"
