#!/bin/sh
# Builds the installable Joomla plugin: dist/plg_system_ytrutube_<version>.zip
#   ./build.sh          lint + test + build
#   ./build.sh --fast   build only
set -e

ROOT=$(cd "$(dirname "$0")" && pwd)
SRC="$ROOT/src/plg_system_ytrutube"
VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$SRC/ytrutube.xml")

if [ "$1" != "--fast" ]; then
    echo "Checking versions ($VERSION)..."
    grep -q "VERSION = '$VERSION'" "$SRC/modules/src/RutubeHelper.php" || { echo "Version mismatch in RutubeHelper.php"; exit 1; }
    grep -q "<version>$VERSION</version>" "$ROOT/updates/plg_system_ytrutube.xml" || { echo "Version mismatch in updates/plg_system_ytrutube.xml"; exit 1; }

    echo "Linting PHP..."
    find "$SRC" "$ROOT/tests" -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null

    echo "Checking JSON..."
    for json in "$SRC"/modules/element/*/element.json "$SRC"/modules/languages/*.json; do
        php -r 'json_decode(file_get_contents($argv[1]), false, 512, JSON_THROW_ON_ERROR);' "$json" || { echo "Invalid JSON: $json"; exit 1; }
    done

    if command -v node > /dev/null; then
        echo "Checking JavaScript..."
        node --check "$SRC/modules/assets/js/ytrutube.js"
    fi

    echo "Running tests..."
    php "$ROOT/tests/parse_url_test.php" > /dev/null
fi

mkdir -p "$ROOT/dist"
OUT="$ROOT/dist/plg_system_ytrutube_$VERSION.zip"
rm -f "$OUT"
(cd "$SRC" && zip -qrX "$OUT" . -x '.*' '*/.*')

echo "Built $OUT"
