#!/usr/bin/env bash

# Build Script for Infynion Logpilot

PLUGIN_SLUG="logpilot"
VERSION=$(grep -E '^\s*\*\s*Version:' "logpilot.php" | awk '{print $3}')
BUILD_DIR="build"
ZIP_NAME="${PLUGIN_SLUG}-v${VERSION}.zip"

echo "Building version ${VERSION}..."

mkdir -p "$BUILD_DIR"

# Ensure composer requires no dev dependencies
composer install --no-dev --optimize-autoloader

# Generate / update the translation template (.pot)
echo "Generating language .pot file..."
mkdir -p languages
wp i18n make-pot . languages/logpilot.pot \
    --slug="$PLUGIN_SLUG" \
    --domain="logpilot" \
    --exclude="vendor,tests,bin,build,node_modules" \
    --allow-root 2>/dev/null || true
echo "Language file: languages/logpilot.pot"

# Create temp dir for zip
TEMP_DIR="/tmp/${PLUGIN_SLUG}"
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

# Copy files
rsync -avz --exclude=".git" \
    --exclude=".github" \
    --exclude="node_modules" \
    --exclude="tests" \
    --exclude="bin" \
    --exclude="phpunit.xml.dist" \
    --exclude="phpcs.xml.dist" \
    --exclude="composer.json" \
    --exclude="composer.lock" \
    --exclude="*.zip" \
    --exclude=".gitignore" \
    --exclude=".phpunit.result.cache" \
    --exclude="patchwork.json" \
    --exclude=".DS_Store" \
    --exclude="$BUILD_DIR" \
    ./ "$TEMP_DIR/"

# Create Zip
cd /tmp
zip -r "$ZIP_NAME" "$PLUGIN_SLUG"
cd -

mv "/tmp/$ZIP_NAME" "$BUILD_DIR/"
rm -rf "$TEMP_DIR"

echo "Build complete: $BUILD_DIR/$ZIP_NAME"

# Restore dev dependencies
composer install
