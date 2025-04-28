<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 00:04:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithHydrateBasket
{
    use WithIntervalsAggregators;

    private function getModelField(Group|Organisation|Shop $model): string
    {
        return match (class_basename($model)) {
            'Group' => 'group_id',
            'Organisation' => 'organisation_id',
            'Shop' => 'shop_id',
        };
    }

    protected function getBasketCountStats(string $dateField, Group|Organisation|Shop $model, ?array $intervals = null, ?array $doPreviousPeriods = null): array
    {
        $stats = [];

        $modelField = $this->getModelField($model);

        $queryBase = Order::where($modelField, $model->id)->where('state', OrderStateEnum::CREATING)->selectRaw(' count(*) as  sum_aggregate');

        return $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: $dateField == 'created_at' ? 'baskets_created_' : 'baskets_updated_',
            dateField: $dateField,
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );
    }

    protected function getBasketNetAmountStats(string $dateField, string $currencyType, Group|Organisation|Shop $model, ?array $intervals = null, ?array $doPreviousPeriods = null): array
    {
        $stats = [];

        $currencyField = match ($currencyType) {
            'shop' => 'net_amount',
            'org' => 'org_net_amount',
            'grp' => 'grp_net_amount',
        };

        $statFieldCurrencySuffix = match ($currencyType) {
            'shop' => '',
            'org' => 'org_currency_',
            'grp' => 'grp_currency_',
        };

        $modelField = $this->getModelField($model);

        $queryBase = Order::where($modelField, $model->id)
            ->where('state', OrderStateEnum::CREATING)
            ->selectRaw('sum('.$currencyField.') as  sum_aggregate');

        return $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: $dateField == 'created_at' ? 'baskets_created_'.$statFieldCurrencySuffix : 'baskets_updated_'.$statFieldCurrencySuffix,
            dateField: $dateField,
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );
    }
}
