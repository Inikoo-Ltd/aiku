<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 00:57:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Goods\StockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockFamilyHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(StockFamily $stockFamily, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        $uniqueId = $stockFamily->id;
        if ($intervals !== null) {
            $uniqueId .= '-'.implode('-', $intervals);
        }
        if ($doPreviousPeriods !== null) {
            $uniqueId .= '-'.implode('-', $doPreviousPeriods);
        }

        return $uniqueId;
    }

    public function handle(StockFamily $stockFamily, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {

        $stats = [];

        $queryBase = DeliveryNoteItem::where('sales_type', DeliveryNoteItemSalesTypeEnum::B2B)->where('stock_family_id', $stockFamily->id)->selectRaw('sum(grp_revenue_amount) as sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_b2b_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $stockFamily->salesIntervals()->update($stats);
    }

}
