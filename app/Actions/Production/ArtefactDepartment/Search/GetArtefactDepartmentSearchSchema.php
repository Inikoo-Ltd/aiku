<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactDepartmentSearchSchema
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
                        'name'     => 'code',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'name',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'slug',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'production_id',
                        'type' => 'int64',
                    ],
                    [
                        'name' => 'organisation_id',
                        'type' => 'int64',
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
