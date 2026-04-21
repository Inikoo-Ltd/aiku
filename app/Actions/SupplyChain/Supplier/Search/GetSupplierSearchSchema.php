<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Apr 2026 15:50:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Search;

use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierSearchSchema
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
                        'name'     => 'agent_id',
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
                        'name' => 'identity_document_number',
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
                'query_by' => 'code,name.contact_name,email,phone,company_name,contact_website,identity_document_number'
            ],
        ];
    }
}
