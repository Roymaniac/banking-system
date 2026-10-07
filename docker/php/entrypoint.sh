#!/bin/sh
set -eu

# Volumes can be empty on their first mount. Create Laravel's writable folders
# without changing ownership or requiring root privileges.
mkdir -p \
    bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Database migrations are deliberately not run here. Deployments must run them
# as a controlled, observable step before new application containers start.
exec "$@"

