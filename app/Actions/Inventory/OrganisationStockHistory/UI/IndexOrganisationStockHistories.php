<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 15:39:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
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

        $sameCurrency = $organisation->currency_id === $organisation->group->currency_id;

        $select = [
            'id',
            'date as bucket',
            'org_stock_value',
            'number_locations',
            'number_org_stocks',
            'number_out_of_stock_org_stocks',
            'percentage_out_of_stock',
            'percentage_value_dormant_stock_1y',
            'value_dormant_stock_1y',
            'number_org_stocks_not_sold_1y',
            'number_location_org_stocks',
            DB::raw("'".$organisation->currency->code."' as org_currency_code"),
        ];

        if (!$sameCurrency) {
            $select[] = 'grp_stock_value';
            $select[] = DB::raw("'".$organisation->group->currency->code."' as grp_currency_code");
        }

        return QueryBuilder::for(OrganisationStockHistory::class)
            ->select($select)
            ->where('organisation_id', $organisation->id)
            ->when($bucket === 'weekly', fn($q) => $q->where('is_week', true))
            ->when($bucket === 'monthly', fn($q) => $q->where('is_month', true))
            ->when($bucket === 'yearly', fn($q) => $q->where('is_year', true))
            ->defaultSort('-date')
            ->allowedSorts([
                AllowedSort::field('bucket', 'date'),
                AllowedSort::field('number_org_stocks'),
                AllowedSort::field('number_out_of_stock_org_stocks'),
                AllowedSort::field('org_stock_value'),
                AllowedSort::field('grp_stock_value'),
            ])
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
                'weekly' => __('Week'),
                'monthly' => __('Month'),
                'yearly' => __('Year'),
                default => __('Date'),
            };

            $sameCurrency = $organisation->currency_id === $organisation->group->currency_id;

            $table
                ->withLabelRecord([__('record'), __('records')])
                ->column(key: 'bucket', label: $bucketLabel, canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'number_org_stocks', label: __('Total SKUs'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_out_of_stock_org_stocks', label: __('Out of Stock'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_locations', label: __('Locations'), canBeHidden: false, sortable: true, align: 'right')
                ->column(
                    key: 'org_stock_value',
                    label: $sameCurrency ? __('Stock Value') : __('Stock Value').' ('.$organisation->currency->code.')',
                    canBeHidden: false,
                    sortable: true,
                    type: 'currency',
                    align: 'right'
                );

            //            if (!$sameCurrency) {
            //                $table->column(key: 'grp_stock_value', label: __('Stock Value').' ('.$organisation->group->currency->code.')', canBeHidden: false, sortable: true, type: 'currency', align: 'right');
            //            }


            $table->column(key: 'number_org_stocks_not_sold_1y', label: __('No sold 1Y'), icon: 'fal fa-ban', tooltip: __('Number of SKUs not sold in more than 1 year'), canBeHidden: false, sortable: true, align: 'right');
            $table->column(
                key: 'value_dormant_stock_1y',
                label: __('Dormant 1Y'),
                icon: 'fal fa-skull-cow',
                tooltip: __('Value of dormant stock for more than 1 year'),
                canBeHidden: false,
                sortable: true,
                type: 'currency',
                align: 'right'
            );
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
                'tabs'           => [
                    'current'    => $this->tab,
                    'navigation' => OrganisationStockHistoriesTabsEnum::navigation(),
                ],

                OrganisationStockHistoriesTabsEnum::DAILY->value => $this->tab == OrganisationStockHistoriesTabsEnum::DAILY->value
                    ? fn() => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn() => OrganisationStockHistoriesResource::collection($this->handle($this->organisation))),

                OrganisationStockHistoriesTabsEnum::WEEKLY->value => $this->tab == OrganisationStockHistoriesTabsEnum::WEEKLY->value
                    ? fn() => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn() => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'weekly'))),

                OrganisationStockHistoriesTabsEnum::MONTHLY->value => $this->tab == OrganisationStockHistoriesTabsEnum::MONTHLY->value
                    ? fn() => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn() => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'monthly'))),

                OrganisationStockHistoriesTabsEnum::YEARLY->value => $this->tab == OrganisationStockHistoriesTabsEnum::YEARLY->value
                    ? fn() => OrganisationStockHistoriesResource::collection($histories)
                    : Inertia::lazy(fn() => OrganisationStockHistoriesResource::collection($this->handle($this->organisation, 'yearly'))),
            ]
        )
            ->table($this->tableStructure($this->organisation))
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
