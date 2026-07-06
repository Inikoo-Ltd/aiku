#
# Author: Raul Perusquia <raul@inikoo.com>
# Created: Sat, 15 Nov 2025 11:11:19 Central Indonesia Time, Kuala Lumpur, Malaysia
# Copyright (c) 2025, Raul A Perusquia Flores
#

echo "Restarting Varnish..."
#sudo varnishadm 'ban req.url ~ .'
sudo varnishadm 'ban obj.http.x-aiku-website ~ .'
echo "Done."
id -un