<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrganisationStockHistory\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\UI\Inventory\OrganisationStockHistoriesTabsEnum;
use App\Http\Resources\Inventory\OrganisationStockHistoriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexOrganisationStockHistories extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrganisationStockHistoriesTabsEnum::values());

        return $this->handle($organisation, $this->tab);
    }

    public function handle(Organisation $organisation, string $period = 'daily'): LengthAwarePaginator
    {
        $pageName = $period . 'Page';
        $perPage  = config('ui.table.records_per_page', 25);

        if ($period === 'daily') {
            $query = DB::table('organisation_stock_histories')
                ->selectRaw('date as period, org_stock_value, grp_stock_value, org_stock_commercial_value, grp_stock_commercial_value, number_org_stocks, number_out_of_stock_org_stocks, number_location_org_stocks')
                ->where('organisation_id', $organisation->id)
                ->orderBy('date', 'desc');

            $this->applyDateFilter($query, $period);

            return $query->paginate(perPage: $perPage, pageName: $pageName)->appends(request()->query());
        }

        $truncUnit = match ($period) {
            'weekly'  => 'week',
            'monthly' => 'month',
            'yearly'  => 'year',
        };

        $query = DB::table('organisation_stock_histories')
            ->selectRaw(
                "DATE_TRUNC('{$truncUnit}', date) as period,
                ROUND(AVG(org_stock_value::numeric), 2) as org_stock_value,
                ROUND(AVG(grp_stock_value::numeric), 2) as grp_stock_value,
                ROUND(AVG(org_stock_commercial_value::numeric), 2) as org_stock_commercial_value,
                ROUND(AVG(grp_stock_commercial_value::numeric), 2) as grp_stock_commercial_value,
                ROUND(AVG(number_org_stocks)) as number_org_stocks,
                ROUND(AVG(number_out_of_stock_org_stocks)) as number_out_of_stock_org_stocks,
                ROUND(AVG(number_location_org_stocks)) as number_location_org_stocks"
            )
            ->where('organisation_id', $organisation->id);

        $this->applyDateFilter($query, $period);

        return $query
            ->groupByRaw("DATE_TRUNC('{$truncUnit}', date)")
            ->orderByRaw("DATE_TRUNC('{$truncUnit}', date) DESC")
            ->paginate(perPage: $perPage, pageName: $pageName)->appends(request()->query());
    }

    private function applyDateFilter(Builder $query, string $period): void
    {
        $filters  = request()->input('between', []);
        $timezone = resolveTimezoneHeader();

        if (!isset($filters['date'])) {
            return;
        }

        $parts = explode('-', $filters['date']);

        if (count($parts) !== 2) {
            return;
        }

        [$start, $end] = array_map('trim', $parts);

        $startDate = Carbon::createFromFormat('Ymd', $start, $timezone)->setTimezone('UTC')->startOfDay()->toDateTimeString();
        $endDate   = Carbon::createFromFormat('Ymd', $end, $timezone)->setTimezone('UTC')->endOfDay()->toDateTimeString();

        $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function tableStructure(string $period = 'daily'): Closure
    {
        return function (InertiaTable $table) use ($period) {
            $table
                ->name($period)
                ->pageName($period . 'Page');

            $periodLabel = match ($period) {
                'weekly'  => __('Week'),
                'monthly' => __('Month'),
                'yearly'  => __('Year'),
                default   => __('Date'),
            };

            $table
                ->withLabelRecord([__('record'), __('records')])
                ->betweenDates(['date'])
                ->column(key: 'period', label: $periodLabel, canBeHidden: false, type: 'date')
                ->column(key: 'number_org_stocks', label: __('Total SKUs'), canBeHidden: false, align: 'right')
                ->column(key: 'number_out_of_stock_org_stocks', label: __('Out of Stock'), canBeHidden: false, align: 'right')
                ->column(key: 'number_location_org_stocks', label: __('In Locations'), canBeHidden: false, align: 'right')
                ->column(key: 'org_stock_value', label: __('Stock Value (Org)'), canBeHidden: false, type: 'currency', align: 'right')
                ->column(key: 'grp_stock_value', label: __('Stock Value (Grp)'), canBeHidden: false, type: 'currency', align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $histories, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrganisationStockHistories',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Stock History'),
                'pageHead'    => [
                    'title' => __('Stock History'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-history'],
                        'title' => __('Stock History'),
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => OrganisationStockHistoriesTabsEnum::navigation(),
                ],

                OrganisationStockHistoriesTabsEnum::DAILY->value => $this->tab == OrganisationStockHistoriesTabsEnum::DAILY->value
                    ? fn () => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn () => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'daily'))),

                OrganisationStockHistoriesTabsEnum::WEEKLY->value => $this->tab == OrganisationStockHistoriesTabsEnum::WEEKLY->value
                    ? fn () => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn () => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'weekly'))),

                OrganisationStockHistoriesTabsEnum::MONTHLY->value => $this->tab == OrganisationStockHistoriesTabsEnum::MONTHLY->value
                    ? fn () => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn () => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'monthly'))),

                OrganisationStockHistoriesTabsEnum::YEARLY->value => $this->tab == OrganisationStockHistoriesTabsEnum::YEARLY->value
                    ? fn () => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn () => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'yearly'))),
            ]
        )
            ->table($this->tableStructure('daily'))
            ->table($this->tableStructure('weekly'))
            ->table($this->tableStructure('monthly'))
            ->table($this->tableStructure('yearly'));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Stock History'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
