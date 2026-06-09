#!/usr/bin/env bash
#
# Build a self-contained plugin zip including vendor/ for GitHub release.
# Run from the plugin root: ./scripts/build-release.sh [version]
#
# The zip is placed in dist/ and named sr-agent-markdown.zip so that it can
# be attached to a GitHub release. Plugin Update Checker uses this asset
# (enableReleaseAssets with /\.zip$/) instead of the auto-generated source zip.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DIST_DIR="$PLUGIN_DIR/dist"
BUILD_DIR="$PLUGIN_DIR/build"
ZIP_NAME="sr-agent-markdown.zip"

cd "$PLUGIN_DIR"

VERSION="${1:-$(grep "Version:" "$PLUGIN_DIR/sr-agent-markdown.php" | sed 's/.*: *//' | tr -d ' ')}"

echo "Building $ZIP_NAME (version $VERSION)..."

rm -rf "$BUILD_DIR" "$DIST_DIR"
mkdir -p "$BUILD_DIR" "$DIST_DIR"

if [[ ! -d vendor ]] || [[ vendor/autoload.php -ot composer.json ]]; then
  echo "Running composer install --no-dev..."
  composer install --no-dev --no-interaction
fi

echo "Copying plugin files..."
rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='build' \
  --exclude='/dist' \
  --exclude='.DS_Store' \
  --exclude='*.log' \
  --exclude='scripts' \
  --exclude='.github' \
  --exclude='tests' \
  --exclude='.phpunit.cache' \
  --exclude='phpunit.xml' \
  . "$BUILD_DIR/sr-agent-markdown/"

echo "Creating zip..."
cd "$BUILD_DIR"
zip -r "$DIST_DIR/$ZIP_NAME" "sr-agent-markdown" -x "*.git*" -x "*.DS_Store"
cd "$PLUGIN_DIR"

rm -rf "$BUILD_DIR"

echo "Built: $DIST_DIR/$ZIP_NAME"
echo "Attach this file to the v$VERSION GitHub release."
