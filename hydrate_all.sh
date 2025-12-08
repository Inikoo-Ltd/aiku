#!/bin/bash

#
# Author: Raul Perusquia <raul@inikoo.com>  
# Created: Sun, 08 Dec 2024 21:30:46 Malaysia Time, Kuala Lumpur, Malaysia
# Copyright (c) 2024, Raul A Perusquia Flores
#

php artisan hydrate:groups
php artisan hydrate:organisations
php artisan hydrate:shops
php artisan hydrate:fulfilments
php artisan hydrate:websites
php artisan hydrate:warehouses
php artisan hydrate:agents
