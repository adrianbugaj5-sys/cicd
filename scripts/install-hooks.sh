#!/bin/sh
cp .githooks/pre-push .git/hooks/pre-push
chmod +x .git/hooks/pre-push
echo "Git hooks zainstalowane."
