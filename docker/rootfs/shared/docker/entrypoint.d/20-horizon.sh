#!/usr/bin/env bash
: "${ENTRYPOINT_ROOT:="/docker"}"

# shellcheck source=SCRIPTDIR/../helpers.sh
source "${ENTRYPOINT_ROOT}/helpers.sh"

entrypoint-set-script-name "$0"

git init
git config --global --add safe.directory /var/www

run-as-runtime-user php artisan horizon:publish
