#!/bin/bash

#
# Author: Raul Perusquia <raul@inikoo.com>
# Created: Sat, 15 Nov 2025 09:25:15 Central Indonesia Time, Kuala Lumpur, Malaysia
# Copyright (c) 2025, Raul A Perusquia Flores
#

VERSION='0.3'
USER_AGENT="cache-warmer/$VERSION (warming cache for varnish)"
# We want to warm the cache twice: as logged in and logged out
STATUSES=("In" "Out")
STATUSES=("Out")
# Warm cache for a given host using a plain-text URL list at /<warming_list_url>
# Each line in the list must be a full URL. Lines starting with '#' or blank lines are ignored.
# Usage: warm_varnish <host> <warming_list_url>
warm_varnish() {
    local host="$1"
    local warming_list_url="$2"
    echo "Warming cache for ${host} using URL list: /${warming_list_url}"
    # Fetch the URL list once (no special headers needed) and for each line warm both statuses
    curl -sS -L --retry 2 --retry-delay 1 -A "$USER_AGENT" "http://${host}/${warming_list_url}" |
    while IFS= read -r line; do
        # Trim leading/trailing whitespace (portable bash approach)
        # shellcheck disable=SC2001
        local trimmed
        trimmed="${line}"
        # Skip blank lines and comments
        if [[ -z "${trimmed//[[:space:]]/}" ]] || [[ "$trimmed" =~ ^[[:space:]]*# ]]; then
            continue
        fi
        # Only process absolute http/https URLs
        if [[ ! "$trimmed" =~ ^https?:// ]]; then
            echo "  ! Skipping non-URL line: $trimmed"
            continue
        fi
        echo "  -> $trimmed"
        # For each URL, request both logged-in and logged-out variants
        for STATUS in "${STATUSES[@]}"; do
            
            local HEADER="X-Warm-Logged-Status: ${STATUS}"
            #echo "  -> [$STATUS] $trimmed  $HEADER "
            # First request: only X-Warm-Logged-Status
            # Delay 100ms before the first request to avoid bursting
            sleep 0.1
            curl -sS --retry 1 --retry-delay 1 \
                 -A "$USER_AGENT" -H "$HEADER" \
                 "$trimmed" -o /dev/null 2>&1

            # Second request: add X-Inertia: true header
#            curl -sS --retry 1 --retry-delay 1 \
#                 -A "$USER_AGENT" -H "$HEADER" -H "X-Inertia: true" \
#                 "$trimmed" -o /dev/null 2>&1

        done
    done
    echo "Done warming cache for ${host}"
}

# --- CLI ---
# First argument is the plain-text list warming_list_url (served by the host). Defaults to warming_base.txt
WARMING_LIST_PATH="${1:-warming_base.txt}"
shift || true

if [ "$#" -eq 0 ]; then
  echo "Usage: $0 <list_warming_list_url> <host1> [host2 ...]"
  echo "Examples:"
  echo "  $0 warming_base.txt example.com another.example.com"
  echo "  $0 warming_products.txt example.com"
  exit 1
fi

for host in "$@"; do
    warm_varnish "$host" "$WARMING_LIST_PATH"
done