#!/usr/bin/env python3
import argparse
import hashlib
import shutil
import sys
import tempfile
import xml.etree.ElementTree as ET
from pathlib import Path


def fail(message):
    print(f"ERROR: {message}", file=sys.stderr)
    return 1


def normalize_text(value):
    return (value or "").replace("\r\n", "\n").replace("\r", "\n")


def sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def main():
    parser = argparse.ArgumentParser(description="Validate ProBG Team against a clean OpenCart 3 source tree.")
    parser.add_argument("--opencart-root", required=True, help="Path to the OpenCart upload/ directory")
    parser.add_argument("--version", required=True, help="OpenCart version label used in the report")
    args = parser.parse_args()

    repo_root = Path(__file__).resolve().parents[1]
    module_upload = repo_root / "upload"
    install_xml = repo_root / "install.xml"
    opencart_root = Path(args.opencart_root).resolve()

    if not module_upload.is_dir():
        return fail("Module upload/ directory is missing")
    if not install_xml.is_file():
        return fail("install.xml is missing")
    if not opencart_root.is_dir():
        return fail(f"OpenCart root does not exist: {opencart_root}")

    expected_core_markers = [
        opencart_root / "index.php",
        opencart_root / "admin" / "index.php",
        opencart_root / "catalog" / "controller" / "startup" / "seo_url.php",
        opencart_root / "system" / "startup.php",
    ]
    missing_markers = [str(path) for path in expected_core_markers if not path.is_file()]
    if missing_markers:
        return fail("Not a complete OpenCart upload tree; missing: " + ", ".join(missing_markers))

    xml_root = ET.parse(install_xml).getroot()
    errors = []
    warnings = []
    checked_operations = 0
    matched_targets = 0

    print(f"OpenCart {args.version}: checking OCMOD targets and anchors")

    for file_node in xml_root.findall("file"):
        pattern = (file_node.get("path") or "").strip().replace("\\", "/")
        operations = file_node.findall("operation")
        if not pattern:
            errors.append("OCMOD <file> entry has no path")
            continue

        matches = sorted(path for path in opencart_root.glob(pattern) if path.is_file())
        required_file = any((operation.get("error") or "").lower() != "skip" for operation in operations)

        if not matches:
            message = f"OCMOD target not found: {pattern}"
            if required_file:
                errors.append(message)
            else:
                warnings.append(message + " (all operations are error=skip)")
            continue

        matched_targets += len(matches)

        for target in matches:
            try:
                target_text = normalize_text(target.read_text(encoding="utf-8"))
            except UnicodeDecodeError:
                target_text = normalize_text(target.read_text(encoding="utf-8", errors="replace"))

            relative_target = target.relative_to(opencart_root).as_posix()

            for index, operation in enumerate(operations, start=1):
                checked_operations += 1
                search_node = operation.find("search")
                needle = normalize_text(search_node.text if search_node is not None else "")
                skippable = (operation.get("error") or "").lower() == "skip"

                if not needle:
                    message = f"{relative_target}: operation {index} has an empty search anchor"
                    if skippable:
                        warnings.append(message)
                    else:
                        errors.append(message)
                    continue

                if needle not in target_text:
                    preview = needle.strip().splitlines()[0][:120] if needle.strip() else "<empty>"
                    message = f"{relative_target}: operation {index} anchor missing: {preview!r}"
                    if skippable:
                        warnings.append(message + " (error=skip)")
                    else:
                        errors.append(message)

    module_files = sorted(path for path in module_upload.rglob("*") if path.is_file())
    collisions = []
    for module_file in module_files:
        relative = module_file.relative_to(module_upload)
        core_file = opencart_root / relative
        if core_file.exists():
            collisions.append(relative.as_posix())

    if collisions:
        errors.append(
            "Package would replace OpenCart core files: " + ", ".join(collisions[:20])
            + (" ..." if len(collisions) > 20 else "")
        )

    print(f"OpenCart {args.version}: checking clean package overlay")
    with tempfile.TemporaryDirectory(prefix="probg-team-compat-") as temp_dir:
        overlay = Path(temp_dir) / "upload"
        shutil.copytree(opencart_root, overlay)
        shutil.copytree(module_upload, overlay, dirs_exist_ok=True)

        for module_file in module_files:
            relative = module_file.relative_to(module_upload)
            installed_file = overlay / relative
            if not installed_file.is_file():
                errors.append(f"Overlay lost module file: {relative.as_posix()}")
                continue
            if sha256(module_file) != sha256(installed_file):
                errors.append(f"Overlay changed module file bytes: {relative.as_posix()}")

    for warning in warnings:
        print(f"WARNING: {warning}")

    print(
        f"OpenCart {args.version}: {matched_targets} OCMOD target files, "
        f"{checked_operations} operations, {len(module_files)} module files checked"
    )

    if errors:
        for error in errors:
            print(f"ERROR: {error}", file=sys.stderr)
        print(f"OpenCart {args.version}: compatibility FAILED with {len(errors)} error(s)", file=sys.stderr)
        return 1

    print(f"OpenCart {args.version}: compatibility OK ({len(warnings)} warning(s))")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
