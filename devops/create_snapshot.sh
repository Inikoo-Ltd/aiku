#!/bin/bash
cd devel || exit
php ../../vendor/bin/envoy run snapshot --buildStep="$1"
