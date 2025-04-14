<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:49:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithHydrateStockSalesIntervals
{
    use AsAction;
    use WithIntervalUniqueJob;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

    public string $rawSqlGrpRevenue = 'sum(grp_revenue_amount) as sum_aggregate';


    public function getStockableJobUniqueId(Stock|StockFamily $stockable, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): string
    {
        $uniqueId = $this->getUniqueJobWithInterval($stockable, $intervals, $doPreviousPeriods);

        if ($onlyProcessSalesType !== null) {
            $uniqueId .= '-'.$onlyProcessSalesType->value;
        }

        return $uniqueId;
    }


    public function handleStockable(Stock|StockFamily $stockable, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): void
    {
        $stats = [];

        $queryBase = DeliveryNoteItem::where('sales_type', '!=', DeliveryNoteItemSalesTypeEnum::NA)
            ->where($stockable instanceof Stock ? 'stock_id' : 'stock_family_id', $stockable->id)
            ->selectRaw($this->rawSqlGrpRevenue);
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $stats = $this->salesPerOrganisation(
            $stockable,
            $stats,
            $intervals,
            $doPreviousPeriods
        );
        $stockable->salesIntervals()->update($stats);


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
        $stockable->salesIntervals()->update($stats);
    }

    public function perSalesType(Stock|StockFamily $stockable, array $stats, DeliveryNoteItemSalesTypeEnum $salesType, ?array $intervals, ?array $doPreviousPeriods): array
    {
        $queryBase = DeliveryNoteItem::where('sales_type', DeliveryNoteItemSalesTypeEnum::B2B)
            ->where($stockable instanceof Stock ? 'stock_id' : 'stock_family_id', $stockable->id)
            ->selectRaw($this->rawSqlGrpRevenue);

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_'.$salesType->value.'_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );
        $this->salesPerOrganisationSalesType($stockable, $stats, $salesType, $intervals, $doPreviousPeriods);

        return $stats;
    }

    public function salesPerOrganisation(Stock|StockFamily $stockable, array $stats, ?array $intervals, ?array $doPreviousPeriods): array
    {
        $organisations = $stockable->group->organisations()->where('organisations.type', OrganisationTypeEnum::SHOP)->orderBy('id')->pluck('id');

        foreach ($organisations as $organisationID) {
            $queryBase = DeliveryNoteItem::where('organisation_id', $organisationID)
                ->where('sales_type', '!=', DeliveryNoteItemSalesTypeEnum::NA)
                ->where($stockable instanceof Stock ? 'stock_id' : 'stock_family_id', $stockable->id)->selectRaw($this->rawSqlGrpRevenue);

            $organisationIntervalData = $this->getIntervalsData(
                stats: [],
                queryBase: $queryBase,
                statField: '',
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            foreach ($organisationIntervalData as $intervalSuffix => $value) {
                data_set(
                    $stats,
                    'revenue_data_'.$intervalSuffix.'.grp_currency.'.$organisationID,
                    $value
                );
            }
        }

        return $stats;
    }

    public function salesPerOrganisationSalesType(Stock|StockFamily $stockable, array $stats, DeliveryNoteItemSalesTypeEnum $salesType, ?array $intervals, ?array $doPreviousPeriods): array
    {
        $organisations = $stockable->group->organisations()->where('organisations.type', OrganisationTypeEnum::SHOP)->orderBy('id')->pluck('id');

        foreach ($organisations as $organisationID) {
            $queryBase = DeliveryNoteItem::where('organisation_id', $organisationID)
                ->where('sales_type', DeliveryNoteItemSalesTypeEnum::B2B)
                ->where($stockable instanceof Stock ? 'stock_id' : 'stock_family_id', $stockable->id)
                ->selectRaw($this->rawSqlGrpRevenue);

            $organisationIntervalData = $this->getIntervalsData(
                stats: [],
                queryBase: $queryBase,
                statField: '',
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );


            foreach ($organisationIntervalData as $intervalSuffix => $value) {
                data_set(
                    $stats,
                    'revenue_'.$salesType->value.'_data_'.$intervalSuffix.'.grp_currency.'.$organisationID,
                    $value
                );
            }
        }

        return $stats;
    }


}
