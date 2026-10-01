#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
mkdir -p reports
echo '[1/3] Rules, CLI and equivalence tests'
while IFS= read -r source; do
    php -l "$source" >/dev/null
done < <(find src bin scripts tests examples -name '*.php' -type f)
php -l bootstrap.php >/dev/null
php tests/run.php | tee reports/tests.txt
echo '[2/3] Automated Othello match'
php bin/othello.php --demo > reports/game.txt
tail -n 16 reports/game.txt
echo '[3/3] CCCC analysis and comparison'
php scripts/analyze.php
echo 'Open reports/complexity.html to view the report.'
