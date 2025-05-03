<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:49:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithHydrateOrgStockSalesIntervals
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

    public string $rawSqlOrgRevenue = 'sum(org_revenue_amount) as sum_aggregate';


    public function getStockableJobUniqueId(OrgStock|OrgStockFamily $stockable, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): string
    {
        $uniqueId = $this->getUniqueJobWithInterval($stockable, $intervals, $doPreviousPeriods);

        if ($onlyProcessSalesType !== null) {
            $uniqueId .= '-'.$onlyProcessSalesType->value;
        }

        return $uniqueId;
    }


    public function handleStockable(OrgStock|OrgStockFamily $stockable, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): void
    {
        $stats = [];

        $queryBase = DeliveryNoteItem::where('sales_type', '!=', DeliveryNoteItemSalesTypeEnum::NA)
            ->where($stockable instanceof OrgStock ? 'org_stock_id' : 'org_stock_family_id', $stockable->id)
            ->selectRaw($this->rawSqlOrgRevenue);
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        if ($onlyProcessSalesType) {
            $salesTypes = [$onlyProcessSalesType];
        } else {
            $salesTypes = DeliveryNoteItemSalesTypeEnum::cases();
        }

        foreach ($salesTypes as $salesType) {
            if ($salesType == DeliveryNoteItemSalesTypeEnum::NA) {
                continue;
            }
            $stats = array_merge(
                $stats,
                $this->perSalesType($stockable, $stats, $salesType, $intervals, $doPreviousPeriods)
            );
        }
        $stockable->salesIntervals->update($stats);
    }

    public function perSalesType(OrgStock|OrgStockFamily $stockable, array $stats, DeliveryNoteItemSalesTypeEnum $salesType, ?array $intervals, ?array $doPreviousPeriods): array
    {
        $queryBase = DeliveryNoteItem::where('sales_type', DeliveryNoteItemSalesTypeEnum::B2B)
            ->where($stockable instanceof OrgStock ? 'org_stock_id' : 'org_stock_family_id', $stockable->id)
            ->selectRaw($this->rawSqlOrgRevenue);

        return $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_'.$salesType->value.'_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

    }

}
