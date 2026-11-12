<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Dec 2023 14:10:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

return [
    'groups' => [
        'grp'         => ['grp.*'],
        'iris'        => ['iris.*'],
        'retina'      => ['retina.*', 'iris.json.fetch_basket', 'iris.models.order.*', 'iris.models.transaction.*', 'iris.models.transactions.*', 'iris.models.favourites.*', 'iris.models.remind_back_in_stock.*', 'iris.json.variant', 'iris.json.basket_transaction_product_data', 'iris.json.shopping_assistant.*'],
        'aiku-public' => ['aiku-public.*'],
        'pupil'      => ['pupil.*'],
    ],
];
