#!/bin/bash
#
# Runs the varnishtest cases in this folder against devops/varnish/default.vcl.
# The VCL is rendered for a plain local Varnish: no querystring vmod, no probe,
# every backend pointed at the test server. Needs varnishtest (brew install varnish).
#
# Usage: devops/varnish/tests/run.sh

set -euo pipefail

DIR=$(cd "$(dirname "$0")" && pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

perl -0pe '
    s/^vcl 4\.1;\n//m;
    s/^import querystring;\n//m;
    s/^.*tracking_params_filter.*\n//mg;
    s/probe replica_pool_probe \{.*?\n\}\n//s;
    s/^\s*\.probe = replica_pool_probe;\n//mg;
    s/"\{\{VARNISH_HOST_(LITIO|BORO)\}\}"/"\${s1_addr}"/g;
    s/"\{\{VARNISH_PORT_LITIO(_REPLICA)?\}\}"/"\${s1_port}"/g;
    s/\.port = "8080";/.port = "\${s1_port}";/g;
' "$DIR/../default.vcl" > "$TMP/default.vcl"

for case in "$DIR"/*.vtc; do
    perl -0pe 'BEGIN { local $/; open my $f, "<", shift @ARGV; $vcl = <$f> } s/__VCL__/$vcl/' "$TMP/default.vcl" "$case" > "$TMP/$(basename "$case")"
    if ! varnishtest -q "$TMP/$(basename "$case")"; then
        echo "FAIL $(basename "$case")" >&2
        failed=1
        continue
    fi
    echo "ok   $(basename "$case")"
done

exit "${failed:-0}"
