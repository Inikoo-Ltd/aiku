<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:48:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\Hydrators;

use App\Actions\Goods\WithHydrateStockSalesIntervals;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Goods\Stock;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class StockHydrateSalesIntervals implements ShouldBeUnique
{
    use WithHydrateStockSalesIntervals;

    public function getJobUniqueId(Stock $stock, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): string
    {
        return $this->getStockableJobUniqueId(
            $stock,
            $intervals,
            $doPreviousPeriods,
            $onlyProcessSalesType
        );
    }

    public function handle(Stock $stock, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): void
    {
        $this->handleStockable(
            $stock,
            $intervals,
            $doPreviousPeriods,
            $onlyProcessSalesType
        );
    }


}
