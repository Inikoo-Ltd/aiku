<?php
/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-16h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Box\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\BoxesResource;
use App\Http\Resources\Dispatching\ShippersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\Box;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBoxes extends OrgAction
{
    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Box::class);
        $query->where('organisation_id', $parent->id);

        return $query->defaultSort('id')
            ->allowedSorts(['id'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function jsonResponse(LengthAwarePaginator $boxes): AnonymousResourceCollection
    {
        return BoxesResource::collection($boxes);
    }

    public function htmlResponse(LengthAwarePaginator $boxes, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/Boxes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Boxes'),
                'pageHead'    => [
                    'title' => __('Boxes'),
                    'icon'  => 'fal fa-box',
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Create New Boxes'),
                            'label'   => __('Create Box'),
                            'route'   => [
                                'name'       => 'grp.org.warehouses.show.dispatching.boxes.create',
                                'parameters' => [
                                    'organisation' => $this->organisation->slug,
                                    'warehouse'         => $this->warehouse->slug,
                                ]
                            ]
                        ],
                    ]
                ],

                'data' => BoxesResource::collection($boxes)
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure($prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('no boxes exist'),
                        'count' => 0,
                    ]
                )
                ->withModelOperations($modelOperations);


            $table->column(key: 'name', label: __('name'), canBeHidden: false, searchable: true);
            $table->column(key: 'dimension', label: __('dimension'), canBeHidden: false, searchable: true);
            $table->column(key: 'stock', label: __('stock'), canBeHidden: false, searchable: true);
        };
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Boxes'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.boxes.index' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.boxes.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                    ]
                )
            ),
            default => []

        };
    }
}
