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

# Create temp dir for zip
TEMP_DIR="/tmp/${PLUGIN_SLUG}"
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

# Copy files
rsync -avz --exclude=".git" \
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
