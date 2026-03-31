<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory\Hydrators;

use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationStockHistoryHydrateFromOrgStockHistories implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(?int $organisationStockHistory): int
    {
        return $organisationStockHistory ?? 0;
    }

    public function handle(?int $organisationStockHistoryId): void
    {
        if (!$organisationStockHistoryId) {
            return;
        }
        $organisationStockHistory = OrganisationStockHistory::find($organisationStockHistoryId);
        if (!$organisationStockHistory) {
            return;
        }

        $stockData = DB::table('org_stock_histories')

            ->selectRaw('COUNT(DISTINCT org_stock_id) as number_org_stocks')
            ->selectRaw('COUNT(DISTINCT CASE WHEN quantity_in_locations < 1 THEN org_stock_id END) as number_out_of_stock_org_stocks')
            ->where('organisation_stock_history_id', $organisationStockHistory->id)
            ->first();

        $organisationStockHistory->update([
            'number_org_stocks'              => $stockData->number_org_stocks,
            'number_out_of_stock_org_stocks' => $stockData->number_out_of_stock_org_stocks
        ]);
    }


}
