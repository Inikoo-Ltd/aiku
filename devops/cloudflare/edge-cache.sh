#!/bin/bash
#
# Cache anonymous storefront HTML at the Cloudflare edge for 10 minutes, or take that off again.
#
# Writes the rules in edge-cache-rules.json (with __HOST__ replaced) into a zone's
# http_request_transform, http_request_cache_settings and http_response_cache_settings phases.
# Those PUTs replace every rule in the phase, so the script stops if the zone already has rules
# there that are not ours.
#
# Far scraper countries (US, SG, CN, HK, JP, MX, KR, BR; search engines and WARP excluded) get
# their own edge copy for 24 hours: the transform rules add testa=far to the query, which gives
# them a separate cache entry and which Varnish strips before its own lookup and the app.
#
# Why each rule is shaped the way it is (cookie, Inertia, warmer, JSON, no purge): .ai/rules/webpage.md.
# Header checks must lowercase the names: http.request.headers.names[*] keeps the client's case.
#
# It also turns on Tiered Cache with the Smart topology (free on every plan, off by default): without
# it each Cloudflare location keeps its own short-lived copy and quiet hours barely reuse one. --off leaves
# it on, since it only shares copies Cloudflare was already allowed to keep.
#
# Usage: CLOUDFLARE_API_TOKEN=... edge-cache.sh <zone_id> <host> [--off]
#   host  the storefront host, e.g. www.awgifts.eu
#   --off empties the three phases (rollback)

set -euo pipefail

ZONE=${1:?zone id}
HOST=${2:?host, e.g. www.awgifts.eu}
MODE=${3:-on}
TOKEN=${CLOUDFLARE_API_TOKEN:?set CLOUDFLARE_API_TOKEN}
API="https://api.cloudflare.com/client/v4/zones/$ZONE/rulesets/phases"
RULES="$(dirname "$0")/edge-cache-rules.json"

cf() {
    curl -sS -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' "$@"
}

for PHASE in http_request_transform http_request_cache_settings http_response_cache_settings; do
    FOREIGN=$(cf "$API/$PHASE/entrypoint" | jq -r '[.result.rules // [] | .[] | select(.description | startswith("aiku iris:") | not) | .description] | join("; ")')
    if [ -n "$FOREIGN" ]; then
        echo "$PHASE on $ZONE has rules that are not ours, not touching it: $FOREIGN" >&2
        exit 1
    fi
done

for PHASE in http_request_transform http_request_cache_settings http_response_cache_settings; do
    if [ "$MODE" = "--off" ]; then
        BODY='{"rules":[]}'
    else
        BODY=$(jq -c --arg host "$HOST" --arg phase "$PHASE" '.[$phase] | walk(if type == "string" then gsub("__HOST__"; $host) else . end)' "$RULES")
    fi
    cf -X PUT "$API/$PHASE/entrypoint" -d "$BODY" | jq -r --arg phase "$PHASE" 'if .success then "\($phase): \(.result.rules | length) rule(s)" else "\($phase) FAILED: \(.errors)" end'
done

if [ "$MODE" != "--off" ]; then
    ZONE_API="https://api.cloudflare.com/client/v4/zones/$ZONE"
    for SETTING in argo/tiered_caching cache/tiered_cache_smart_topology_enable; do
        cf -X PATCH "$ZONE_API/$SETTING" -d '{"value":"on"}' | jq -r --arg s "$SETTING" 'if .success then "\($s): \(.result.value)" else "\($s) FAILED: \(.errors)" end'
    done
fi
