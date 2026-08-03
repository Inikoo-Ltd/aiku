<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchOrders
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $ordersQuery = Order::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $ordersQuery->where('shop_id', $shopId);
        }

        return [
            'scope'   => 'orders',
            'results' => [
                'orders' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['reference'] ?? null,
                    'name'  => ($document['contact_name'] ?? null) ?: ($document['company_name'] ?? null),
                    'state' => $document['state'] ?? null,
                ], $this->rawDocuments($ordersQuery)),
            ],
        ];
    }


}
