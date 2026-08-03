<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchSupplyChain
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query): array
    {
        return [
            'scope'   => 'supply_chain',
            'results' => [
                'suppliers' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['code'] ?? null,
                    'name'  => $document['name'] ?? null,
                    'email' => $document['email'] ?? null,
                    'phone' => $document['phone'] ?? null,
                    'state' => ($document['status'] ?? false) ? 'active' : 'inactive',
                ], $this->rawDocuments(Supplier::search($query))),
            ],
        ];
    }


}
