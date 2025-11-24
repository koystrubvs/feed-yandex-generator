#!/bin/bash
echo "Running PHPStan Level 8 analysis..."
if ! docker ps | grep -q "wordpress-local"; then
    echo "ERROR: wordpress-local container is not running!"
    exit 1
fi
PLUGIN_PATH="wp-content/plugins/yandex-feed-generator-pro-v2"
CHANGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep "^$PLUGIN_PATH.*\.php$")
if [ -z "$CHANGED_FILES" ]; then
    echo "No plugin PHP files changed, skipping PHPStan"
    exit 0
fi
echo "Changed files: $CHANGED_FILES"
docker exec wordpress-local bash -c "cd /var/www/html/wp-content/plugins/yandex-feed-generator-pro-v2 && vendor/bin/phpstan analyse --level=8 --error-format=raw --no-progress" > /tmp/phpstan-output.txt 2>&1
PHPSTAN_EXIT_CODE=$?
if [ $PHPSTAN_EXIT_CODE -eq 0 ]; then
    echo "PHPStan: No errors found!"
    exit 0
else
    echo "PHPStan found errors:"
    cat /tmp/phpstan-output.txt
    echo "COMMIT BLOCKED: Fix PHPStan errors first!"
    echo "Bypass: git commit --no-verify"
    exit 1
fi