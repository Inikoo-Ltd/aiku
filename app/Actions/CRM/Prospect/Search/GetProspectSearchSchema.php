<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetProspectSearchSchema
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
                        'name'     => 'shop_id',
                        'type'     => 'int32',
                        'optional' => true,
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
                        'name' => 'contact_name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'company_name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'contact_website',
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
                'query_by' => 'name,contact_name,email,phone,company_name,contact_website'
            ],
        ];
    }
}
