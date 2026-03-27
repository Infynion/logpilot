#!/usr/bin/env bash

# Setup pre-commit hook
HOOK_DIR=".git/hooks"
PRE_COMMIT_FILE="$HOOK_DIR/pre-commit"

if [ ! -d "$HOOK_DIR" ]; then
    echo "Notice: not a git repository root. Skipping git hook installation."
    exit 0
fi

cat << 'EOF' > "$PRE_COMMIT_FILE"
#!/usr/bin/env bash

echo "Running PHP Linting and CodeSniffer..."

# Lint changed PHP files
FILES=$(git diff --cached --name-only --diff-filter=ACM | grep ".php$")

if [ "$FILES" = "" ]; then
    exit 0
fi

PASS=true

for FILE in $FILES
do
    php -l -d display_errors=0 "$FILE"
    if [ $? != 0 ]; then
        echo "PHP Syntax Error in $FILE"
        PASS=false
    fi
done

echo "Running PHPCS..."
./vendor/bin/phpcs --standard=phpcs.xml.dist $FILES

if [ $? != 0 ]; then
    echo "PHPCS found violations. Please fix them before committing."
    PASS=false
fi

if ! $PASS; then
    echo "Commit aborted."
    exit 1
fi

exit 0
EOF

chmod +x "$PRE_COMMIT_FILE"
echo "Git pre-commit hook installed successfully."
