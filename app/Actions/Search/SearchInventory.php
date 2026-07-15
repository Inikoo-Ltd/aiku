<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 11:03:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchInventory
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $organisationId = Arr::get($options, 'organisation_id');
        $orgStocksQuery = OrgStock::search($query);
        if ($organisationId) {
            $orgStocksQuery->where('organisation_id', $organisationId);
        }

        $orgStockFamiliesQuery = OrgStockFamily::search($query);
        if ($organisationId) {
            $orgStockFamiliesQuery->where('organisation_id', $organisationId);
        }

        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? null,
        ];

        return [
            'scope'   => 'inventory',
            'results' => [
                'org_stocks'         => array_map($mapCodeNameState, $this->rawDocuments($orgStocksQuery)),
                'org_stock_families' => array_map($mapCodeNameState, $this->rawDocuments($orgStockFamiliesQuery)),
            ],
        ];
    }


}
