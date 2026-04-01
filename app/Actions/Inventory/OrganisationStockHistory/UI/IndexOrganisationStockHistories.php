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
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedSort;

class IndexOrganisationStockHistories extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrganisationStockHistoriesTabsEnum::values());

        return $this->handle($organisation, $this->tab);
    }

    public function handle(Organisation $organisation, string $bucket = 'daily'): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($bucket);

        return QueryBuilder::for(OrganisationStockHistory::class)
            ->select([
                'id',
                'date as bucket',
                'org_stock_value',
                'grp_stock_value',
                'org_stock_commercial_value',
                'grp_stock_commercial_value',
                'number_org_stocks',
                'number_out_of_stock_org_stocks',
                'number_location_org_stocks',
                DB::raw("'" . $organisation->currency->code . "' as org_currency_code"),
                DB::raw("'" . $organisation->group->currency->code . "' as grp_currency_code"),
            ])
            ->where('organisation_id', $organisation->id)
            ->when($bucket === 'weekly', fn ($q) => $q->where('is_week', true))
            ->when($bucket === 'monthly', fn ($q) => $q->where('is_month', true))
            ->when($bucket === 'yearly', fn ($q) => $q->where('is_year', true))
            ->when($bucket === 'daily', fn ($q) => $q->where('is_week', false)->where('is_month', false)->where('is_year', false))
            ->defaultSort('-date')
            ->allowedSorts([AllowedSort::field('bucket', 'date')])
            ->withPaginator($bucket, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, string $bucket = 'daily'): Closure
    {
        return function (InertiaTable $table) use ($organisation, $bucket) {
            $table
                ->name($bucket)
                ->pageName($bucket.'Page');

            $bucketLabel = match ($bucket) {
                'weekly'  => __('Week'),
                'monthly' => __('Month'),
                'yearly'  => __('Year'),
                default   => __('Date'),
            };

            $orgCurrency = $organisation->currency->code;
            $grpCurrency = $organisation->group->currency->code;

            $table
                ->withLabelRecord([__('record'), __('records')])
                ->column(key: 'bucket', label: $bucketLabel, canBeHidden: false, type: 'date', sortable: true)
                ->column(key: 'number_org_stocks', label: __('Total SKUs'), canBeHidden: false, align: 'right')
                ->column(key: 'number_out_of_stock_org_stocks', label: __('Out of Stock'), canBeHidden: false, align: 'right')
                ->column(key: 'number_location_org_stocks', label: __('In Locations'), canBeHidden: false, align: 'right')
                ->column(key: 'org_stock_value', label: __('Stock Value').' ('.$orgCurrency.')', canBeHidden: false, type: 'currency', align: 'right')
                ->column(key: 'grp_stock_value', label: __('Stock Value').' ('.$grpCurrency.')', canBeHidden: false, type: 'currency', align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $histories, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrganisationStockHistories',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'          => __('Stock History'),
                'pageHead'       => [
                    'title' => __('Stock History'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-history'],
                        'title' => __('Stock History'),
                    ],
                ],
                'download_route' => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.export',
                    'parameters' => $request->route()->originalParameters(),
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
            ->table($this->tableStructure($this->organisation, 'daily'))
            ->table($this->tableStructure($this->organisation, 'weekly'))
            ->table($this->tableStructure($this->organisation, 'monthly'))
            ->table($this->tableStructure($this->organisation, 'yearly'));
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
