<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Apr 2026 22:30:55 Nepal Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetDeliveryNoteSearchSchema
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
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'company_name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'contact_name',
                        'type' => 'string',
                    ],
                    [
                        'name'     => 'tracking',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'customer_name',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'customer_reference',
                        'type'     => 'string',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'order_references',
                        'type'     => 'string[]',
                        'optional' => true,
                    ],
                    [
                        'name'     => 'address',
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
                'query_by' => 'reference,tracking,order_references,customer_reference,company_name,contact_name,customer_name,address'
            ],
        ];
    }
}
