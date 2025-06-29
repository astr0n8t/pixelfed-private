#!/usr/bin/env bash
# shellcheck disable=SC2119

: "${ENTRYPOINT_ROOT:="/docker"}"

# shellcheck source=SCRIPTDIR/../helpers.sh
source "${ENTRYPOINT_ROOT}/helpers.sh"

entrypoint-set-script-name "$0"

acquire-lock

su www-data -s /bin/bash -c 'cd /var/www && git init && git config --global --add safe.directory /var/www'
# Copy the [storage/] skeleton files over the "real" [storage/] directory so assets are updated between versions
run-as-runtime-user cp --force --recursive storage.skel/. ./storage/

# Ensure storage link are correctly configured
run-as-runtime-user php artisan storage:link
