#!/bin/bash

# Reusable plugin slug (folder name and main file basename)
SLUG="boardscribe"
DIST_DIR="./dist"
ZIP_NAME="${SLUG}.zip"
EXTRACT_DIR="${DIST_DIR}/${SLUG}"
MAIN_FILE="${EXTRACT_DIR}/${SLUG}.php"

# Flag default
KEEP_BUILD_FOLDER=false
SHOW_HELP=false

# Parse boolean flags by presence
for arg in "$@"; do
  case "$arg" in
    --keep-build-folder) KEEP_BUILD_FOLDER=true ;;
    --help|-h) SHOW_HELP=true ;;
  esac
done

if [ "$SHOW_HELP" = true ]; then
  echo "Usage: $0 [--keep-build-folder]"; exit 0; fi

echo "KEEP_BUILD_FOLDER=$KEEP_BUILD_FOLDER"

# Ensure dist directory exists
mkdir -p "$DIST_DIR"

# Build initial zip. The contents come from package.json's "files" allowlist,
# so anything that must ship has to be listed there.
npx wp-scripts plugin-zip --no-root-folder || { echo "ERROR: wp-scripts plugin-zip failed"; exit 1; }

# Clear previous extracted folder
rm -rfd "${EXTRACT_DIR:?}"

# Unzip build into extract dir
unzip "$ZIP_NAME" -d "$EXTRACT_DIR" > /dev/null || { echo "ERROR: unzip failed"; exit 1; }

# Some systems plugin-zip might work different on and create an extra folder level
if [ -d "${EXTRACT_DIR:?}/${SLUG:?}" ]; then
  shopt -s dotglob nullglob
  mv "${EXTRACT_DIR:?}/${SLUG:?}/"* "${EXTRACT_DIR:?}/"
  rm -r "${EXTRACT_DIR:?}/${SLUG:?}"
  shopt -u dotglob nullglob
fi

# npm always packs package.json and the README alongside whatever the "files"
# allowlist says, so the allowlist alone cannot keep them out - they have to be
# deleted here. LICENSE is force-included the same way, but that one ships.
[ -f "$EXTRACT_DIR/package.json" ] && rm "$EXTRACT_DIR/package.json"
[ -f "$EXTRACT_DIR/README.md" ] && rm "$EXTRACT_DIR/README.md"

# Strip macOS cruft (e.g. assets/images/.DS_Store) before zipping
find "$EXTRACT_DIR" -name '.DS_Store' -delete

# Remove original build zip
rm "$ZIP_NAME"

# Extract version from main plugin file header (first matching Version: line)
if [ ! -f "$MAIN_FILE" ]; then
  echo "ERROR: Main plugin file not found at $MAIN_FILE"; exit 1; fi
VERSION=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9][0-9A-Za-z._-]*\).*/\1/p' "$MAIN_FILE" | head -n1)
if [ -z "$VERSION" ]; then
  echo "ERROR: Could not extract version from $MAIN_FILE"; grep -n "Version" "$MAIN_FILE" || true; exit 1; fi

# vendor/ ships because the plugin bootstraps through Composer's PSR-4
# autoloader. With no runtime Composer dependency it should be autoload.php
# plus composer/ and nothing else - anything more means the --no-dev install
# did not take, and a zip built from a dev vendor/ is both bloated and wrong.
if [ -d "$EXTRACT_DIR/vendor" ]; then
  UNEXPECTED=""
  for entry in "$EXTRACT_DIR"/vendor/* "$EXTRACT_DIR"/vendor/.[!.]*; do
    [ -e "$entry" ] || continue
    name=$(basename "$entry")
    case "$name" in
      autoload.php|composer) ;;
      *) UNEXPECTED="$UNEXPECTED $name" ;;
    esac
  done
  if [ -n "$UNEXPECTED" ]; then
    echo "ERROR: unexpected entries in vendor/ - run 'composer install --no-dev --optimize-autoloader' first:" >&2
    echo "$UNEXPECTED" >&2
    exit 1
  fi
else
  echo "ERROR: vendor/ missing from the staged plugin - the autoloader must ship" >&2
  exit 1
fi

echo "Building plugin package for version: $VERSION"

# Create final distributable zip. WordPress derives the install path from the
# single top-level directory, so it has to be named exactly like the slug.
# Built under a temp name and moved into place only once complete - zipping
# straight to $FINAL_ZIP would leave a truncated file sitting at the real
# release path if zip failed partway (disk full, permissions), indistinguishable
# from a good build without re-checking. The temp file stays inside $DIST_DIR
# so the final mv is a same-filesystem rename, not a copy.
cd "$DIST_DIR" || exit 1
FINAL_ZIP="${SLUG}-${VERSION}.zip"
TMP_ZIP=".${FINAL_ZIP}.tmp"
rm -f "$TMP_ZIP"
zip -r "$TMP_ZIP" "$SLUG" > /dev/null || { echo "ERROR: zip failed"; rm -f "$TMP_ZIP"; exit 1; }

# Remove .po files. zip -d exits 12 for "nothing to do" (no .po files
# matched) - that's fine, but any other nonzero status is a real failure
# and must not let a zip that still contains .po files reach $FINAL_ZIP.
zip -d "$TMP_ZIP" "${SLUG}/languages/*.po" > /dev/null
ZIP_D_STATUS=$?
if [ "$ZIP_D_STATUS" -ne 0 ] && [ "$ZIP_D_STATUS" -ne 12 ]; then
  echo "ERROR: zip -d failed removing .po files (exit $ZIP_D_STATUS)" >&2
  rm -f "$TMP_ZIP"
  exit 1
fi

# mv -f overwrites $FINAL_ZIP directly (a same-filesystem rename, so this
# is atomic) instead of rm-then-mv, which would leave no zip at all at the
# release path if the mv step failed after the rm already ran.
mv -f "$TMP_ZIP" "$FINAL_ZIP" || { echo "ERROR: mv failed"; rm -f "$TMP_ZIP"; exit 1; }

cd ..

# Optionally clean extracted folder
if [ "$KEEP_BUILD_FOLDER" = false ]; then
  rm -r "${EXTRACT_DIR:?}"
fi

echo "Done: $FINAL_ZIP"
