#!/usr/bin/env bash
#
# Point aiku's web addresses at a new server (INI-133). Every Cloudflare zone the token can
# see, A records only: each record on one of the old addresses gets the new one, proxy
# setting unchanged. Shows the list and changes nothing unless --apply is given; every change
# is written to dns-switch-<date>.log, so moving to the next server is the same command again.
#
# The token: Cloudflare dashboard > My Profile > API Tokens > Create token > "Edit zone DNS",
# Zone resources: All zones from every account it may use. The person makes it; then
#     export CF_API_TOKEN=...
#
# Usage:
#     dns-switch.sh NEW_IP                    # preview: what would change
#     dns-switch.sh NEW_IP --apply            # change it
#     dns-switch.sh NEW_IP --from A,B --apply # other old addresses (default: litio and boro)
#
# helio's address (65.109.156.41: WordPress sites, staging) is left alone: not part of aiku.
#
set -euo pipefail

new_ip=${1:?usage: dns-switch.sh NEW_IP [--from OLD,OLD] [--apply]}
shift
old_ips=65.109.156.59,65.109.156.60
apply=0
while [[ $# -gt 0 ]]; do
  case $1 in
    --apply) apply=1 ;;
    --from) old_ips=${2:?}; shift ;;
    *) echo "FAIL: unknown option $1"; exit 1 ;;
  esac
  shift
done
: "${CF_API_TOKEN:?FAIL: export CF_API_TOKEN first (see the top of this script)}"

api() {
  local method=$1 path=$2 data=${3:-}
  curl -fsS -X "$method" "https://api.cloudflare.com/client/v4$path" \
    -H "Authorization: Bearer $CF_API_TOKEN" -H "Content-Type: application/json" ${data:+--data "$data"}
}

log=dns-switch-$(date -u +%Y%m%d-%H%M%S).log
old_json=$(jq -cn --arg o "$old_ips" '$o | split(",")')
count=0
page=1
while :; do
  zones=$(api GET "/zones?per_page=50&page=$page")
  while read -r zone_id zone_name; do
    [[ -z $zone_id ]] && continue
    records=$(api GET "/zones/$zone_id/dns_records?type=A&per_page=500" |
      jq -r --argjson old "$old_json" '.result[] | select(.content as $c | $old | index($c)) | "\(.id) \(.name) \(.content)"')
    while read -r record_id name content; do
      [[ -z $record_id ]] && continue
      count=$((count + 1))
      if [[ $apply == 1 ]]; then
        api PATCH "/zones/$zone_id/dns_records/$record_id" "{\"content\":\"$new_ip\"}" >/dev/null
        echo "$zone_name $name $content -> $new_ip" | tee -a "$log"
      else
        echo "would change: $name $content -> $new_ip"
      fi
    done <<<"$records"
  done < <(jq -r '.result[] | "\(.id) \(.name)"' <<<"$zones")
  [[ $page -ge $(jq -r '.result_info.total_pages' <<<"$zones") ]] && break
  page=$((page + 1))
done

if [[ $apply == 1 ]]; then
  echo "OK: $count records now point at $new_ip (list in $log)"
else
  echo "OK: $count records would change. Nothing changed yet: run again with --apply"
fi
