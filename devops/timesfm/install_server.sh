#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if ! python3 -c "import ensurepip" 2>/dev/null; then
    sudo NEEDRESTART_SUSPEND=1 apt-get install -y python3-venv
fi

python3 -m venv .venv
.venv/bin/pip install -q --upgrade pip
.venv/bin/pip install -q torch --index-url https://download.pytorch.org/whl/cpu
.venv/bin/pip install -q 'timesfm[torch,xreg]' 'psycopg[binary]'
.venv/bin/pip install -q -U jax jaxlib
.venv/bin/pip list 2>/dev/null | awk '/^(nvidia-|jax-cuda)/ {print $1}' | xargs -r .venv/bin/pip uninstall -y -q

for version in 3 2.5; do
    TIMESFM_VERSION=$version nice -n 19 .venv/bin/python -u forecaster.py 2>&1 | grep -v -i warn
done
