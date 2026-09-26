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
        'retina'      => ['retina.*', 'iris.json.fetch_basket', 'iris.models.order.*', 'iris.models.transaction.*'],
        'aiku-public' => ['aiku-public.*'],
        'cornea'      => ['cornea.*'],
        'pupil'      => ['pupil.*'],
    ],
];
