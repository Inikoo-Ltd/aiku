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
use App\Http\Resources\Inventory\OrganisationStockHistoriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedSort;

class IndexOrganisationStockHistories extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    protected function getElementGroups(Organisation $organisation): array
    {
        return [
            'bucket' => [
                'label'    => __('Period'),
                'elements' => [
                    'daily'   => [__('Daily')],
                    'weekly'  => [__('Weekly')],
                    'monthly' => [__('Monthly')],
                    'yearly'  => [__('Yearly')],
                ],
                'engine' => function ($query, $elements) {
                    $query->where(function ($q) use ($elements) {
                        foreach ($elements as $element) {
                            $q->orWhere(function ($inner) use ($element) {
                                match ($element) {
                                    'weekly'  => $inner->where('is_week', true),
                                    'monthly' => $inner->where('is_month', true),
                                    'yearly'  => $inner->where('is_year', true),
                                    default   => $inner->where('is_week', false)->where('is_month', false)->where('is_year', false),
                                };
                            });
                        }
                    });
                },
            ],
        ];
    }

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(OrganisationStockHistory::class)
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
            ])
            ->where('organisation_id', $organisation->id);

        foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: null
            );
        }

        return $queryBuilder
            ->defaultSort('-date')
            ->allowedSorts([AllowedSort::field('bucket', 'date')])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            foreach ($this->getElementGroups($this->organisation) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withLabelRecord([__('record'), __('records')])
                ->column(key: 'bucket', label: __('Date'), canBeHidden: false, type: 'date', sortable: true)
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
                'data' => fn () => OrganisationStockHistoriesResource::collection($histories),
            ]
        )->table($this->tableStructure());
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
