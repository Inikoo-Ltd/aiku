<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:44:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\UI\Dispatch\TrolleysTabsEnum;
use App\Http\Resources\Dispatching\TrolleysResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTrolleys extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trolleys.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Trolley::class)
            ->where('trolleys.warehouse_id', $warehouse->id);

        return $query
            ->select([
                'trolleys.id',
                'trolleys.name',
                'trolleys.slug',
            ])
            ->defaultSort('trolleys.name')
            ->allowedSorts(['name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title'       => __('No picking trolleys found'),
                    'description' => $this->canEdit ? __('Get started by creating a new picking trolley. ✨') : null,
                    'count'       => 0,
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New picking trolley'),
                        'label'   => __('picking trolley'),
                        'route'   => [
                            'name'       => 'grp.org.warehouses.show.dispatching.trolleys.create',
                            'parameters' => [
                                request()->route('organisation'),
                                request()->route('warehouse'),
                            ],
                        ],
                    ] : null,
                ])
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'delivery_note', label: __('Current Delivery Note'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('name');
        };
    }

    public function htmlResponse(LengthAwarePaginator $pickingTrolleys, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/Trolleys',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Picking trolleys'),
                'pageHead'                        => [
                    'title'  => __('Picking trolleys'),
                    'icon'   => ['fal', 'dolly-flatbed-alt'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Create'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.trolleys.create',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'tabs'                            => [
                    'current'    => $this->tab,
                    'navigation' => TrolleysTabsEnum::navigation(),
                ],
                TrolleysTabsEnum::TROLLEYS->value => TrolleysResource::collection($pickingTrolleys),
            ]
        )->table($this->tableStructure(prefix: TrolleysTabsEnum::TROLLEYS->value));
    }

    public function jsonResponse(LengthAwarePaginator $trolleys): AnonymousResourceCollection
    {
        return TrolleysResource::collection($trolleys);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(TrolleysTabsEnum::values());

        return $this->handle($warehouse, TrolleysTabsEnum::TROLLEYS->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Picking trolleys'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.trolleys.index' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb([
                    'name'       => 'grp.org.warehouses.show.dispatching.trolleys.index',
                    'parameters' => $routeParameters,
                ])
            ),
            default => []
        };
    }
}
