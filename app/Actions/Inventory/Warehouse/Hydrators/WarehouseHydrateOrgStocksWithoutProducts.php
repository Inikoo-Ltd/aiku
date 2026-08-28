<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateOrgStocksWithoutProducts implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    public function handle(Warehouse $warehouse): void
    {
        $numberOrgStocksWithoutProducts = OrgStock::where('organisation_id', $warehouse->organisation_id)
            ->whereIn('state', [OrgStockStateEnum::ACTIVE, OrgStockStateEnum::DISCONTINUING])
            ->whereNotExists(function ($query) {
                $query->from('product_has_org_stocks')
                    ->whereColumn('product_has_org_stocks.org_stock_id', 'org_stocks.id');
            })
            ->count();

        $warehouse->stats()->update([
            'number_org_stocks_without_products' => $numberOrgStocksWithoutProducts
        ]);
    }
}
