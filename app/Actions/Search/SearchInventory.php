<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 11:03:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Http\Resources\Inventory\OrgStockFamiliesSearchResultResource;
use App\Http\Resources\Inventory\OrgStocksSearchResultResource;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchInventory
{
    use AsAction;

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


        return [
            'scope'   => 'inventory',
            'results' => [
                'org_stocks'         => OrgStocksSearchResultResource::collection($orgStocksQuery->get()),
                'org_stock_families' => OrgStockFamiliesSearchResultResource::collection($orgStockFamiliesQuery->get()),

            ],
        ];
    }


}
