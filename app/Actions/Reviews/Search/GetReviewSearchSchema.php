<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetReviewSearchSchema
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
                        'name' => 'shop_id',
                        'type' => 'int32',
                    ],
                    [
                        'name' => 'organisation_id',
                        'type' => 'int32',
                    ],
                    [
                        'name' => 'status',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'rating',
                        'type' => 'float',
                    ],
                    [
                        'name' => 'customer_name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'message',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'reply_message',
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
                'query_by' => 'customer_name,message,reply_message'
            ],
        ];
    }
}
