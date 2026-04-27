<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Apr 2026 20:31:07 Nepal Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetInvoiceSearchSchema
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
                        'name' => 'type',
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
                        'name' => 'email',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'customer_name',
                        'type' => 'string',
                    ],
                    [
                        'name' => 'customer_contact_name',
                        'type' => 'string',
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
                'query_by' => 'reference'
            ],
        ];
    }
}
