#
# Author: Raul Perusquia <raul@inikoo.com>
# Created: Sat, 15 Nov 2025 11:11:19 Central Indonesia Time, Kuala Lumpur, Malaysia
# Copyright (c) 2025, Raul A Perusquia Flores
#

echo "Restarting Varnish..."
# Bot copies (x-aiku-bot-copy) are spared: bots run no JS, so a pre-deploy page can't break for them.
# varnish:restart (x-ban-all) still flushes everything.
sudo varnishadm 'ban obj.http.x-aiku-website ~ . && obj.http.x-aiku-bot-copy != 1'
echo "Done."
id -un