#!/usr/bin/env bash
#
# Bump version, commit, tag, and push to trigger a GitHub release.
# Run from the plugin root: ./scripts/release.sh <version>
#
# Example: ./scripts/release.sh 1.0.1
#
# This script is excluded from release zips (scripts/ is not in the dist).
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
MAIN_FILE="$PLUGIN_DIR/sr-agent-markdown.php"

if [[ -z "$1" ]]; then
  echo "Usage: $0 <version>"
  echo "Example: $0 1.0.1"
  exit 1
fi

VERSION="$1"
DATE="$(date +%Y-%m-%d)"

cd "$PLUGIN_DIR"

if [[ -n "$(git status --porcelain | grep -v '^??')" ]]; then
  echo "Working tree has uncommitted changes. Commit or stash first."
  exit 1
fi

echo "Releasing v$VERSION..."

sed -i.bak "s/Version: [0-9.]*/Version: $VERSION/" "$MAIN_FILE"
sed -i.bak "s/define( 'SR_AGENT_MARKDOWN_VERSION', '[^']*' );/define( 'SR_AGENT_MARKDOWN_VERSION', '$VERSION' );/" "$MAIN_FILE"
rm -f "$MAIN_FILE.bak"

sed -i.bak "s/Stable tag: [0-9.]*/Stable tag: $VERSION/" readme.txt
rm -f readme.txt.bak

if [[ -f readme.txt ]]; then
  perl -i -0pe "s/(== Changelog ==\n\n)/\1= $VERSION ($DATE) =\n* Release\n\n/" readme.txt
fi

if [[ -f CHANGELOG.md ]]; then
  perl -i -0pe "s/## \[Unreleased\]/## [Unreleased]\n\n## [$VERSION] - $DATE/" CHANGELOG.md
  git add CHANGELOG.md
fi

git add sr-agent-markdown.php readme.txt
git commit -m "v$VERSION: Release"
git tag -a "v$VERSION" -m "Release $VERSION"
git push origin main
git push origin "v$VERSION"

echo "Done. Release workflow: https://github.com/studiorepublic/sr-agent-markdown/actions"
