<?php

namespace App\Actions\Inventory\PickedBay\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\UI\Inventory\PickedBaysTabsEnum;
use App\Http\Resources\Inventory\PickedBayResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\PickedBay;
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

class IndexPickedBays extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('picked_bays.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PickedBay::class)
            ->where('picked_bays.warehouse_id', $warehouse->id);

        return $query
            ->select([
                'picked_bays.id',
                'picked_bays.code',
                'picked_bays.slug',
            ])
            ->defaultSort('picked_bays.code')
            ->allowedSorts(['code'])
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
                    'title'       => __('No picked bays found'),
                    'description' => $this->canEdit ? __('Get started by creating a new picked bay. ✨') : null,
                    'count'       => 0,
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New picked bay'),
                        'label'   => __('picked bay'),
                        'route'   => [
                            'name'       => 'grp.org.warehouses.show.inventory.picked_bays.create',
                            'parameters' => [
                                request()->route('organisation'),
                                request()->route('warehouse'),
                            ],
                        ],
                    ] : null,
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'delivery_note', label: __('Current Delivery Note'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $pickedBays, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Warehouse/PickedBays',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Picked bays'),
                'pageHead'    => [
                    'title'  => __('Picked bays'),
                    'icon'   => ['fal', 'fa-monument'],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Create'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.picked_bays.create',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PickedBaysTabsEnum::navigation(),
                ],
                PickedBaysTabsEnum::BAYS->value => PickedBayResource::collection($pickedBays),
            ]
        )->table($this->tableStructure(prefix: PickedBaysTabsEnum::BAYS->value));
    }

    
    public function jsonResponse(LengthAwarePaginator $pickedBays): AnonymousResourceCollection
    {
        return PickedBayResource::collection($pickedBays);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickedBaysTabsEnum::values());

        return $this->handle($warehouse, PickedBaysTabsEnum::BAYS->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Picked bays'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.picked_bays.index' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb([
                    'name'       => 'grp.org.warehouses.show.dispatching.picked_bays.index',
                    'parameters' => $routeParameters,
                ])
            ),
            default => []
        };
    }
}
