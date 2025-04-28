<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 00:57:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\Hydrators;

use App\Actions\Goods\WithHydrateStockSalesIntervals;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Goods\StockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class StockFamilyHydrateSalesIntervals implements ShouldBeUnique
{
    use  WithHydrateStockSalesIntervals;

    public function getJobUniqueId(StockFamily $stockFamily, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): string
    {
        return $this->getStockableJobUniqueId(
            $stockFamily,
            $intervals,
            $doPreviousPeriods,
            $onlyProcessSalesType
        );
    }

    public function handle(StockFamily $stockFamily, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): void
    {
        $this->handleStockable(
            $stockFamily,
            $intervals,
            $doPreviousPeriods,
            $onlyProcessSalesType
        );
    }

}
