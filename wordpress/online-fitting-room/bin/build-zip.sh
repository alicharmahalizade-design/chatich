#!/usr/bin/env bash
# Builds the CSS and an installable zip without development files (see .distignore).
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=online-fitting-room
VERSION=$(grep -m1 "Version:" online-fitting-room.php | sed 's/.*Version: *//')
npm run -s build:css
BUILD=$(mktemp -d)
mkdir "$BUILD/$SLUG"
rsync -a --exclude-from=.distignore ./ "$BUILD/$SLUG/"
OUT="$(pwd)/../$SLUG-$VERSION.zip"
rm -f "$OUT"
(cd "$BUILD" && zip -qr "$OUT" "$SLUG")
rm -rf "$BUILD"
echo "Built $OUT"
