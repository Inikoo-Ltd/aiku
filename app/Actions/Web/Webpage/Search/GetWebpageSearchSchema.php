<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetWebpageSearchSchema
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
                        'name' => 'group_id',
                        'type' => 'int32',
                    ],
                    [
                        'name' => 'shop_id',
                        'type' => 'int32',
                    ],
                    [
                        'name' => 'website_id',
                        'type' => 'int32',
                    ],
                    [
                        'name' => 'code',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'url',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'title',
                        'type' => 'string',
                    ],
                    [
                        'name'     => 'description',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name' => 'type',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'state',
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
                'query_by' => 'code,url,title,description'
            ],
        ];
    }
}
