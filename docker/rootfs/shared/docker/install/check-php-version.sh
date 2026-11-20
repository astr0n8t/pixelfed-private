#!/bin/bash
# Fail the build if the installed PHP (from packages.sury.org) is older than the latest security release
# for its branch. Bump these when PHP ships a security release.
#
# 2026-11-19 security release: CVE-2026-129289 (PHP-FPM request smuggling RCE), CVE-2026-115560,
# CVE-2026-126714 and others.
set -ex -o errexit -o nounset -o pipefail

declare -A minimum=(
    [8.2]=8.2.36
    [8.3]=8.3.37
    [8.4]=8.4.28
    [8.5]=8.5.13
)

installed=$(php -r 'echo PHP_VERSION;')
branch=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
required=${minimum[${branch}]:-}

if [[ -z "${required}" ]]; then
    echo "No minimum PHP version configured for branch ${branch}; add one to $0"
    exit 1
fi

if ! php -r "exit(version_compare(PHP_VERSION, '${required}', '>=') ? 0 : 1);"; then
    echo "PHP ${installed} is older than the patched release ${required}"
    exit 1
fi

echo "PHP ${installed} >= ${required}"
