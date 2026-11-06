<?php

/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\Catalogue\Shop\SalesTarget\GetShopYearSalesTarget;
use App\Actions\Traits\Authorisations\WithGroupDashboardSalesAuthorisation;
use App\Actions\Traits\Dashboards\Settings\WithDashboardPartnersTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\WithTabsBox;
use App\Actions\UI\Dashboards\GetGroupDashboardTimeSeriesData;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Http\Resources\Dashboards\DashboardShopSalesResource;
use App\Http\Resources\Dashboards\DashboardTotalShopsTimeSeriesSalesResource;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Number;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMobileGroupDashboard
{
    use AsAction;
    use WithTabsBox;
    use WithDashboardIntervalOption;
    use WithDashboardPartnersTypeSettings;
    use WithGroupDashboardSalesAuthorisation;

    public function handle(User $user): array
    {
        $group = $user->group;

        if (!$this->canViewGroupDashboardSales($user)) {
            return [
                'currency_code' => $group->currency->code,
                'intervals'     => [],
                'sales'         => null,
            ];
        }

        $shops     = GetGroupDashboardTimeSeriesData::run($group, null, null, null, $this->dashboardIncludesPartners($user->settings ?? []))['shops']['all'];
        $intervals = array_map(fn (DateIntervalEnum $interval) => $interval->value, DateIntervalEnum::casesWithoutCustom());

        return [
            'currency_code' => $group->currency->code,
            'intervals'     => collect($this->dashboardIntervalOption())->map(fn (array $option) => [
                'value'       => $option['value'],
                'label'       => $option['label'],
                'short_label' => $option['labelShort'],
            ])->values()->all(),
            'sales'         => [
                'pipeline'    => $this->pipeline($group),
                'year_target' => $this->yearTarget(GetShopYearSalesTarget::run($group, $user, null, false)),
                'shops'       => collect(DashboardShopSalesResource::collection($shops)->resolve())->map(fn (array $row) => [
                    'slug'    => $row['slug'],
                    'name'    => Arr::get($row, 'columns.label.formatted_value'),
                    'is_open' => $row['state'] === 'active',
                    'figures' => $this->figures($row['columns'], $intervals),
                ])->values()->all(),
                'totals'      => $this->figures(Arr::get(DashboardTotalShopsTimeSeriesSalesResource::make($shops)->resolve(), 'columns', []), $intervals),
            ],
        ];
    }

    /**
     * The order pipeline as the web's tabs box groups it (In Basket, Submitted, Warehouse…),
     * each group with its total and its steps, amounts formatted in the group currency. A group
     * is keyed by its first step, which does not change with the language.
     */
    private function pipeline(Group $group): array
    {
        return collect($this->getTabsBox($group))->map(function (array $box) {
            $steps     = collect($box['tabs']);
            $hasAmount = $steps->contains(fn (array $tab) => Arr::get($tab, 'information.type') === 'currency');
            $amountOf  = fn (array $tab) => (float) Arr::get($tab, 'information.label', 0);

            return [
                'key'    => $steps->first()['tab_slug'],
                'label'  => $box['label'],
                'count'  => (int) $steps->sum('value'),
                'amount' => $hasAmount ? Number::currency($steps->sum($amountOf), $box['currency_code']) : null,
                'steps'  => $steps->map(fn (array $tab) => [
                    'key'    => $tab['tab_slug'],
                    'label'  => $tab['label'],
                    'count'  => (int) $tab['value'],
                    'amount' => Arr::get($tab, 'information.type') === 'currency'
                        ? Number::currency($amountOf($tab), $box['currency_code'])
                        : null,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    private function yearTarget(array $target): array
    {
        $currency = $target['currency_code'];
        $money    = fn (int|float|null $amount) => $amount === null ? null : Number::currency($amount, $currency);
        $amount   = Arr::get($target, 'target.amount');

        return [
            'label'          => $target['month_label'],
            'sales_so_far'   => $money($target['sales_so_far']),
            'target'         => $money($amount),
            'progress'       => $amount ? round($target['sales_so_far'] / $amount, 4) : null,
            'remaining_days' => $target['remaining_days'],
            'gap'            => $money($target['gap']),
            'needed_per_day' => $money($target['needed_per_day']),
        ];
    }

    /**
     * Invoices and group-currency sales per interval, already formatted by aiku.
     */
    private function figures(array $columns, array $intervals): array
    {
        return collect($intervals)->mapWithKeys(fn (string $interval) => [
            $interval => [
                'invoices'    => Arr::get($columns, "invoices.$interval.formatted_value", '0'),
                'sales'       => Arr::get($columns, "sales_grp_currency_external.$interval.formatted_value"),
                'sales_value' => (float) Arr::get($columns, "sales_grp_currency_external.$interval.raw_value", 0),
            ],
        ])->all();
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user());
    }
}
