#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="fsanz-undeclared-allergen-json"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$REPO_ROOT/dist"

rm -rf "$DIST_DIR/$PLUGIN_SLUG"
rm -f "$DIST_DIR/$PLUGIN_SLUG.zip" "$REPO_ROOT/$PLUGIN_SLUG.zip"
mkdir -p "$DIST_DIR"
cp -R "$REPO_ROOT/$PLUGIN_SLUG" "$DIST_DIR/$PLUGIN_SLUG"
find "$DIST_DIR/$PLUGIN_SLUG" -name ".DS_Store" -delete
(cd "$DIST_DIR" && zip -qr "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG")
cp "$DIST_DIR/$PLUGIN_SLUG.zip" "$REPO_ROOT/$PLUGIN_SLUG.zip"
