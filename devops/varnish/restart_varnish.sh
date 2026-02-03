#
# Author: Raul Perusquia <raul@inikoo.com>
# Created: Sat, 15 Nov 2025 11:11:19 Central Indonesia Time, Kuala Lumpur, Malaysia
# Copyright (c) 2025, Raul A Perusquia Flores
#

echo "Restarting Varnish..."
sudo cp /etc/haproxy/haproxy_no_varnish.cfg /etc/haproxy/haproxy.cfg
sudo systemctl restart haproxy.service
sudo systemctl restart varnish
sudo cp /etc/haproxy/haproxy_varnish.cfg /etc/haproxy/haproxy.cfg
sudo systemctl restart haproxy.service
echo "Done."
#echo "warming base cache...";
#./cache_warmer.sh warming_base.txt WEBSITE_DOMAIN &
#wait
#echo "warming families cache...";
#./cache_warmer.sh warming_families WEBSITE_DOMAIN &