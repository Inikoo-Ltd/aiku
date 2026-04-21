<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Apr 2026 17:59:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetLocationSearchSchema
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
                        'name'     => 'warehouse_id',
                        'type'     => 'int32',
                    ],
                    [
                        'name'     => 'warehouse_area_id',
                        'type'     => 'int32',
                        'optional' => true,
                    ],
                    [
                        'name' => 'status',
                        'type' => 'bool',
                    ],
                    [
                        'name' => 'code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'created_at',
                        'type' => 'int64',
                    ],
                    [
                        'name'     => '__soft_deleted',
                        'type'     => 'int32',
                        'optional' => true,
                    ],
                ],
                'default_sorting_field' => 'created_at',
            ],
            'search-parameters' => [
                'query_by' => 'code'
            ],
        ];
    }
}
