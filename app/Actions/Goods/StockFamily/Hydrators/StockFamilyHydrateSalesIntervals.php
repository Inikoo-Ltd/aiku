<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 00:57:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Goods\StockFamily;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockFamilyHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';
    public string $rawSqlGrpRevenue = 'sum(grp_revenue_amount) as sum_aggregate';

    public function getJobUniqueId(StockFamily $stockFamily, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): string
    {
        $uniqueId = $stockFamily->id;
        if ($intervals !== null) {
            $uniqueId .= '-'.implode('-', $intervals);
        }
        if ($doPreviousPeriods !== null) {
            $uniqueId .= '-'.implode('-', $doPreviousPeriods);
        }

        if ($onlyProcessSalesType !== null) {
            $uniqueId .= '-'.$onlyProcessSalesType->value;
        }

        return $uniqueId;
    }

    public function handle(StockFamily $stockFamily, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): void
    {

        $stats = [];

        $queryBase = DeliveryNoteItem::where('sales_type', '!=', DeliveryNoteItemSalesTypeEnum::NA)->where('stock_family_id', $stockFamily->id)->selectRaw($this->rawSqlGrpRevenue);
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $stats = $this->salesPerOrganisation(
            $stockFamily,
            $stats,
            $intervals,
            $doPreviousPeriods
        );
        $stockFamily->salesIntervals()->update($stats);


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
                $this->perSalesType($stockFamily, $stats, $salesType, $intervals, $doPreviousPeriods)
            );
        }
        $stockFamily->salesIntervals()->update($stats);



    }

    public function perSalesType(StockFamily $stockFamily, array $stats, DeliveryNoteItemSalesTypeEnum $salesType, ?array $intervals, ?array $doPreviousPeriods): array
    {
        $queryBase = DeliveryNoteItem::where('sales_type', DeliveryNoteItemSalesTypeEnum::B2B)->where('stock_family_id', $stockFamily->id)->selectRaw($this->rawSqlGrpRevenue);

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'revenue_'.$salesType->value.'_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );
        $this->salesPerOrganisationSalesType($stockFamily, $stats, $salesType, $intervals, $doPreviousPeriods);

        return $stats;
    }

    public function salesPerOrganisation(StockFamily $stockFamily, array $stats, ?array $intervals, ?array $doPreviousPeriods): array
    {

        $organisations = $stockFamily->group->organisations()->where('organisations.type', OrganisationTypeEnum::SHOP)->orderBy('id')->pluck('id');

        foreach ($organisations as $organisationID) {
            $queryBase = DeliveryNoteItem::where('organisation_id', $organisationID)->where('sales_type', '!=', DeliveryNoteItemSalesTypeEnum::NA)->where('stock_family_id', $stockFamily->id)->selectRaw($this->rawSqlGrpRevenue);

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


    public function salesPerOrganisationSalesType(StockFamily $stockFamily, array $stats, DeliveryNoteItemSalesTypeEnum $salesType, ?array $intervals, ?array $doPreviousPeriods): array
    {

        $organisations = $stockFamily->group->organisations()->where('organisations.type', OrganisationTypeEnum::SHOP)->orderBy('id')->pluck('id');

        foreach ($organisations as $organisationID) {
            $queryBase = DeliveryNoteItem::where('organisation_id', $organisationID)->where('sales_type', DeliveryNoteItemSalesTypeEnum::B2B)->where('stock_family_id', $stockFamily->id)->selectRaw($this->rawSqlGrpRevenue);

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
