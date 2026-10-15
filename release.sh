#!/usr/bin/env bash
# Build the Evermonth Web plugin zip from ../Web, and (with --publish) release it on GitHub.
#
#   bash release.sh 1.0.1             -> builds dist/evermonth-web.zip only (test it first)
#   bash release.sh 1.0.1 --publish   -> also commits, tags v1.0.1, pushes, and creates the GitHub release
#
# WordPress sees the new release as a normal plugin update (within 6 hours, or at once with
# Ajustes > Evermonth Web > Buscar actualización).
set -euo pipefail

VERSION="${1:-}"
PUBLISH="${2:-}"
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Usage: bash release.sh X.Y.Z [--publish]" >&2
  exit 1
fi

HERE="$(cd "$(dirname "$0")" && pwd)"
WEB="$HERE/../Web"
PLUGIN="$HERE/evermonth-web"
BUILD="$HERE/dist/build/evermonth-web"
ZIP="$HERE/dist/evermonth-web.zip"

# 1. Version: plugin header and constant. Both must match the tag.
sed -i -E "s/^( \* Version:[[:space:]]+).*/\1$VERSION/" "$PLUGIN/evermonth-web.php"
sed -i -E "s/define\( 'EVW_VERSION', '[^']*' \)/define( 'EVW_VERSION', '$VERSION' )/" "$PLUGIN/evermonth-web.php"
grep -q "Version:           $VERSION" "$PLUGIN/evermonth-web.php"
grep -q "'EVW_VERSION', '$VERSION'" "$PLUGIN/evermonth-web.php"

# 2. Plugin code + a copy of Web/ as site/ (without ref/, the Figma reference).
rm -rf "$HERE/dist"
mkdir -p "$BUILD"
cp -r "$PLUGIN/." "$BUILD/"
rm -rf "$BUILD/site"
cp -r "$WEB" "$BUILD/site"
rm -rf "$BUILD/site/ref"

# 3. Zip with forward slashes and one top folder "evermonth-web/" (WordPress needs both).
python -I - "$HERE/dist/build" "$ZIP" <<'PY'
import os, sys, zipfile
root, out = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for d, _, files in os.walk(root):
        for f in sorted(files):
            p = os.path.join(d, f)
            z.write(p, os.path.relpath(p, root).replace(os.sep, "/"))
PY
echo "Built $ZIP ($(du -h "$ZIP" | cut -f1)), version $VERSION"

if [ "$PUBLISH" != "--publish" ]; then
  exit 0
fi

# 4. Publish: commit, tag, push, GitHub release with the zip.
cd "$HERE"
git add -A
git commit -m "Evermonth Web $VERSION" || true
git tag "v$VERSION"
git push origin main "v$VERSION"
gh release create "v$VERSION" "$ZIP" --title "Evermonth Web $VERSION" --notes "${NOTES:-Versión $VERSION}"
echo "Released v$VERSION"
