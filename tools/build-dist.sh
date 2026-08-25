#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="$(python3 - <<'PY'
import xml.etree.ElementTree as ET
root = ET.parse('install.xml').getroot()
version = (root.findtext('version') or '').strip()
if not version:
    raise SystemExit('Missing <version> in install.xml')
print(version)
PY
)"

PACKAGE_VERSION="${1:-$VERSION}"
if [[ ! "$PACKAGE_VERSION" =~ ^[0-9A-Za-z._-]+$ ]]; then
  echo "Invalid package version: $PACKAGE_VERSION" >&2
  exit 1
fi

mkdir -p dist
rm -f dist/*.ocmod.zip dist/SHA256SUMS
OUTPUT="dist/probg-team-${PACKAGE_VERSION}.ocmod.zip"

python3 - "$OUTPUT" <<'PY'
import os
import stat
import sys
import zipfile
from pathlib import Path

output = Path(sys.argv[1])
root = Path('.').resolve()
paths = [Path('install.xml')]
paths.extend(sorted(path for path in Path('upload').rglob('*') if path.is_file()))

with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for relative in paths:
        data = relative.read_bytes()
        info = zipfile.ZipInfo(relative.as_posix(), date_time=(1980, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 3
        info.external_attr = (0o644 & 0xFFFF) << 16
        archive.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
PY

unzip -t "$OUTPUT" >/dev/null
(
  cd dist
  sha256sum "$(basename "$OUTPUT")" > SHA256SUMS
)

"$ROOT/tools/validate.sh"

echo "Built: $OUTPUT"
cat dist/SHA256SUMS
