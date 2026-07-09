<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 11:03:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Http\Resources\Inventory\OrgStocksSearchResultResource;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchInventory
{
    use AsAction;

    public function handle(string $query, array $options): array
    {
        $orgStocksQuery = OrgStock::search($query);
        if ($organisationId = Arr::get($options, 'organisation_id')) {
            $orgStocksQuery->where('organisation_id', $organisationId);
        }


        return [
            'scope'   => 'org_stocks',
            'results' => [
                'org_stocks' => OrgStocksSearchResultResource::collection($orgStocksQuery->get()),

            ],
        ];
    }


}
