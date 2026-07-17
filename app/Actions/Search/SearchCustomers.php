<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchCustomers
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $customersQuery = Customer::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $customersQuery->where('shop_id', $shopId);
        }

        $stateIcons = CustomerStateEnum::stateIcon();

        return [
            'scope'   => 'customers',
            'results' => [
                'customers' => array_map(static fn (array $document) => [
                    'id'           => (int)$document['id'],
                    'slug'         => $document['slug'] ?? null,
                    'name'         => $document['name'] ?? null,
                    'location'     => json_decode($document['location'] ?? 'null', true),
                    'state_icon'   => Arr::get($stateIcons, $document['state'] ?? ''),
                    'contact_name' => $document['contact_name'] ?? null,
                    'company_name' => $document['company_name'] ?? null,
                    'email'        => $document['email'] ?? null,
                    'phone'        => $document['phone'] ?? null,
                ], $this->rawDocuments($customersQuery)),
            ],
        ];
    }


}
