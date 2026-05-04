<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Apr 2026 15:41:28 Nepal Time, Kathmandu Airport, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection DuplicatedCode */

namespace App\Actions\Inventory\OrgStock\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockSearchSchema
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
                        'name' => 'code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'state',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'name',
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
                'query_by' => 'code,name'
            ],
        ];
    }
}
