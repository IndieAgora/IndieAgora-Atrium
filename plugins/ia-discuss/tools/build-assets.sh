#!/usr/bin/env bash
set -euo pipefail

# IA Discuss helper: rebuild monolithic runtime JS from the split working files.
# This is OPTIONAL. The plugin runs fine without running this script.
#
# Use when you edit assets/js/split/* and want to copy changes back into the monolithic files
# that are enqueued in includes/support/assets.php.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "[IA Discuss] Root: $ROOT"
echo "NOTE: This is a helper scaffold. It does not attempt 'smart' refactors."
echo "      It simply prints the intended source-of-truth locations."

echo
echo "Monolithic runtime files currently enqueued:"
echo "  - assets/js/ia-discuss.ui.feed.js"
echo "  - assets/js/ia-discuss.ui.search.js"
echo "  - assets/js/ia-discuss.ui.composer.js"
echo "  - assets/js/ia-discuss.router.js"
echo
echo "Split working folders:"
echo "  - assets/js/split/feed/"
echo "  - assets/js/split/search/"
echo "  - assets/js/split/router/"
echo "  - assets/js/split/composer/"
echo
echo "Recommended workflow:"
echo "  1) Edit the split files for clarity."
echo "  2) Manually port the change into the matching monolithic module file."
echo
echo "Future: we can upgrade this script into a real builder once the split files are"
echo "syntactically standalone modules (each file can execute independently)."
