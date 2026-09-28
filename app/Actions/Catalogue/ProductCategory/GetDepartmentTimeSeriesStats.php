<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Helpers\Dashboard\CalculateTimeSeriesStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDepartmentTimeSeriesStats
{
    use AsObject;

    public function handle(Shop $shop, $fromDate = null, $toDate = null): array
    {
        $departments = ProductCategory::query()
            ->select(['id', 'slug', 'code', 'name'])
            ->where('shop_id', $shop->id)
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
            ->where('state', '!=', ProductCategoryStateEnum::DISCONTINUED)
            ->with(['timeSeries' => fn ($query) => $query->select(['id', 'product_category_id'])->where('frequency', TimeSeriesFrequencyEnum::DAILY->value)])
            ->get();

        $timeSeriesIdByDepartment = $departments
            ->mapWithKeys(fn (ProductCategory $department) => [$department->id => $department->timeSeries->first()?->id])
            ->filter();

        $stats = CalculateTimeSeriesStats::run(
            $timeSeriesIdByDepartment->values()->all(),
            [
                'sales_grp_currency_external' => 'sales_grp_currency_external',
                'sales_org_currency_external' => 'sales_org_currency_external',
                'invoices'                    => 'invoices',
                'customers_invoiced'          => 'customers_invoiced',
            ],
            'product_category_time_series_records',
            'product_category_time_series_id',
            $fromDate,
            $toDate
        );

        $groupCurrencyCode = $shop->group->currency?->code ?? 'GBP';

        return $departments
            ->filter(fn (ProductCategory $department) => $timeSeriesIdByDepartment->has($department->id))
            ->map(fn (ProductCategory $department) => array_merge($stats[$timeSeriesIdByDepartment[$department->id]] ?? [], [
                'id'                  => $department->id,
                'slug'                => $department->slug,
                'name'                => $department->name ?: $department->code,
                'group_currency_code' => $groupCurrencyCode,
            ]))
            ->values()
            ->all();
    }
}
