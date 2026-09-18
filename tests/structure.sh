#!/usr/bin/env bash
# Structural checks that php -l cannot make. Runs in CI and by hand.
#
# 1. Exactly one .php file at the package root carries a Plugin Name header.
#    Bedrock's autoloader loads every headed file one level into mu-plugins/,
#    so a second headed file here would be loaded by the autoloader as well as
#    (or instead of) by the loader, and every module defines named functions:
#    loaded twice is a fatal "Cannot redeclare".
# 2. Every file the loader requires exists. A typo there is a fatal on every
#    request of every site, and php -l does not follow require paths.
# 3. Nothing under src/ still reaches a sibling through WPMU_PLUGIN_DIR. That
#    constant names the mu-plugins root, not this package's directory, and the
#    one such line in superrad-mu.php is what had to change when the files
#    moved in here.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
fail=0

headed=0
for f in *.php; do
  if grep -q '^[[:space:]]*\*[[:space:]]*Plugin Name:' "$f"; then
    headed=$((headed + 1))
    echo "  headed at root: $f"
  fi
done
if [[ "$headed" -eq 1 ]]; then
  echo "PASS  one headed file at the package root"
else
  echo "FAIL  $headed headed files at the package root (want exactly 1)"
  fail=1
fi

while IFS= read -r rel; do
  if [[ -f "$rel" ]]; then
    echo "PASS  loader requires $rel"
  else
    echo "FAIL  loader requires $rel, which does not exist"
    fail=1
  fi
done < <(sed -n "s|^require_once __DIR__ \. '/\(.*\)';\$|\1|p" superrad-mu-plugins.php)

if grep -rn 'WPMU_PLUGIN_DIR' src/; then
  echo "FAIL  src/ reaches a sibling through WPMU_PLUGIN_DIR; use __DIR__"
  fail=1
else
  echo "PASS  no WPMU_PLUGIN_DIR paths under src/"
fi

exit "$fail"
