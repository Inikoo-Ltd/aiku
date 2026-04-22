<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 10:35:48 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetOrderSearchSchema
{
    use AsObject;

    public function handle(): array
    {
        return [
            'collection-schema' => [
                'fields'                => [
                    [
                        'name' => 'id',
                        'type' => 'string',
                    ],
                    [
                        'name'     => 'organisation_id',
                        'type'     => 'int32',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'shop_id',
                        'type'     => 'int32',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'customer_id',
                        'type'     => 'int32',
                        'optional' => true,
                    ],
                    [
                        'name' => 'state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'reference',
                        'type' => 'string',
                    ],
                    [
                        'name'     => 'customer_reference',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'date',
                        'type' => 'int64',
                    ],
                    [
                        'name'     => '__soft_deleted',
                        'type'     => 'int32',
                        'optional' => true,
                    ],
                ],
                'default_sorting_field' => 'date',
            ],
            'search-parameters' => [
                'query_by' => 'reference,customer_reference'
            ],
        ];
    }
}
