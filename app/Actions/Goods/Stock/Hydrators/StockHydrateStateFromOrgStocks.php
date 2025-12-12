<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 19:47:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\Hydrators;

use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateStocks;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStocks;
use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockHydrateStateFromOrgStocks implements ShouldBeUnique
{
    use AsAction;
    use WithWeightFromTradeUnits;

    public function getJobUniqueId(int|null $stockID): string
    {
        return $stockID ?? 'empty';
    }

    public function handle(int|null $stockID): void
    {
        if ($stockID == null) {
            return;
        }

        $stock = Stock::find($stockID);
        if (!$stock) {
            return;
        }

        $oldState = $stock->state;
        $state = $this->getStockStateFromOrgStocks($stock);

        $stock->update([
            'state' => $state,
        ]);

        if ($oldState != $state) {
            GroupHydrateStocks::dispatch($stock->group);


            if ($stock->stockFamily) {
                StockFamilyHydrateStocks::dispatch($stock->stockFamily);
            }

        }


    }

    public function getStockStateFromOrgStocks(Stock $stock): StockStateEnum
    {
        $numberOrgStocks = 0;
        $numberDiscontinuedOrgStocks = 0;
        $numberDiscontinuingOrgStocks = 0;
        foreach ($stock->orgStocks as $orgStock) {
            $numberOrgStocks++;
            if ($orgStock->state == OrgStockStateEnum::DISCONTINUED) {
                $numberDiscontinuedOrgStocks++;
            }
            if ($orgStock->state == OrgStockStateEnum::DISCONTINUING) {
                $numberDiscontinuingOrgStocks++;
            }

        }

        if ($numberOrgStocks == 0) {
            return StockStateEnum::IN_PROCESS;
        }

        if ($numberOrgStocks == $numberDiscontinuedOrgStocks) {
            return StockStateEnum::DISCONTINUED;
        }

        if ($numberOrgStocks == $numberDiscontinuingOrgStocks) {
            return StockStateEnum::DISCONTINUING;
        }

        return StockStateEnum::ACTIVE;

    }

}
