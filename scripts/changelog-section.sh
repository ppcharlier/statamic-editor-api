#!/usr/bin/env bash
#
# Print one version's section of CHANGELOG.md, heading stripped.
#
# The Statamic Marketplace does not read this file: it reads the body of a
# GitHub Release. This script is the bridge between the two — what it prints
# is what a buyer reads on statamic.com/addons/ppcharlier/editor-api.
#
#   scripts/changelog-section.sh 2.2.1
#
# Exits 1 when the version has no section, so the caller can decide what to
# do rather than silently publishing empty release notes.

set -euo pipefail

version="${1:?usage: changelog-section.sh <version>   # e.g. 2.2.1}"
changelog="${2:-CHANGELOG.md}"

# Headings look like: ## [2.2.1] — 2026-09-03
# Match on the bracketed version alone; the em dash and date are free to change.
section=$(awk -v want="## [$version]" '
    index($0, want) == 1 { inside = 1; next }   # the heading itself is dropped
    inside && /^## /     { exit }               # the next version ends us
    inside {
        # Hold blank lines back until real content follows them, which trims
        # both the leading and the trailing blanks in a single pass.
        if (NF) { while (pending-- > 0) print ""; pending = 0; print; seen = 1 }
        else if (seen) pending++
    }
' "$changelog")

if [ -z "$section" ]; then
    echo "No CHANGELOG.md section found for version $version" >&2
    exit 1
fi

printf '%s\n' "$section"
