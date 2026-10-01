#!/usr/bin/env bash
# Build an installable WordPress plugin zip in the project root.
# readme.txt is the source of truth; README.md is generated from it.
set -euo pipefail

PLUGIN_SLUG="sikora-tagdiv-post-views-sorter"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ZIP_NAME="${PLUGIN_SLUG}.zip"
ZIP_PATH="${SCRIPT_DIR}/${ZIP_NAME}"
README_MD="${SCRIPT_DIR}/README.md"
README_TXT="${SCRIPT_DIR}/readme.txt"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${PLUGIN_SLUG}.XXXXXX")"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

# Shown in README.md only (not a standard WordPress.org readme.txt header).
AUTHOR_MD='[Sikora Collective](https://sikoracollective.com/)'

cleanup() {
	rm -rf "${BUILD_DIR}"
}
trap cleanup EXIT

# Generate README.md from WordPress readme.txt.
python3 - "${README_TXT}" "${README_MD}" "${AUTHOR_MD}" <<'PY'
import re
import sys
from pathlib import Path

src = Path(sys.argv[1])
dst = Path(sys.argv[2])
author_md = sys.argv[3]

lines = src.read_text(encoding="utf-8").splitlines()
if not lines or not lines[0].startswith("=== ") or not lines[0].endswith(" ==="):
	sys.exit("readme.txt must start with === Plugin Name ===")

title = lines[0][4:-4].strip()
meta = {}
i = 1
while i < len(lines):
	line = lines[i]
	if line.strip() == "":
		i += 1
		break
	if ":" not in line:
		sys.exit(f"readme.txt header is invalid at line {i + 1}: {line}")
	key, val = line.split(":", 1)
	meta[key.strip().lower()] = val.strip()
	i += 1

while i < len(lines) and lines[i].strip() == "":
	i += 1
if i >= len(lines):
	sys.exit("readme.txt is missing a short description.")
short_desc = lines[i].strip()
i += 1
while i < len(lines) and lines[i].strip() == "":
	i += 1

required = [
	"contributors",
	"tags",
	"requires at least",
	"tested up to",
	"requires php",
	"stable tag",
	"license",
	"license uri",
]
missing = [key for key in required if key not in meta]
if missing:
	sys.exit("readme.txt is missing headers: " + ", ".join(missing))

# Map WordPress headers to Markdown metadata labels.
meta_out = [
	("Version", meta["stable tag"]),
	("Author", author_md),
	("Contributors", meta["contributors"]),
	("Tags", meta["tags"]),
	("Requires at least", meta["requires at least"]),
	("Tested up to", meta["tested up to"]),
	("Requires PHP", meta["requires php"]),
	("License", meta["license"]),
	("License URI", meta["license uri"]),
]

out = [f"# {title}", ""]
for label, value in meta_out:
	out.append(f"**{label}:** {value}")
out.append("")
out.append(short_desc)
out.append("")

for line in lines[i:]:
	if re.match(r"^== .+ ==$", line):
		out.append(f"## {line[3:-3].strip()}")
		continue
	if re.match(r"^= .+ =$", line):
		out.append(f"### {line[2:-2].strip()}")
		continue
	if line.startswith("* "):
		out.append("- " + line[2:])
		continue
	out.append(line)

while out and out[-1] == "":
	out.pop()
out.append("")

dst.write_text("\n".join(out), encoding="utf-8")
print(f"Generated {dst}")
PY

mkdir -p "${PLUGIN_DIR}"

# Files included in the installable plugin package.
cp "${SCRIPT_DIR}/${PLUGIN_SLUG}.php" "${PLUGIN_DIR}/"
cp "${README_TXT}" "${PLUGIN_DIR}/"

rm -f "${ZIP_PATH}"
(
	cd "${BUILD_DIR}"
	zip -r "${ZIP_PATH}" "${PLUGIN_SLUG}"
)

echo "Created ${ZIP_PATH}"
