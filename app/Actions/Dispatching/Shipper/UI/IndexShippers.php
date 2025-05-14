<?php
/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-10h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Http\Resources\Accounting\TopUpsResource;
use App\Http\Resources\Dispatching\ShippersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\TopUp;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexShippers extends OrgAction
{
    private Organisation $parent;

    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('code', $value)
                    ->orWhereWith('name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Shipper::class);
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
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $shippers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/Shippers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Shippers'),
                'pageHead'    => [
                    'title' => __('Shippers'),
                    'icon'  => 'fal fa-shopping-basket'
                ],

                'data' => ShippersResource::collection($shippers)
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
                        'title' => __('no shippers exist'),
                        'count' => 0,
                    ]
                )
                ->withModelOperations($modelOperations);


            $table->column(key: 'code', label: __('code'), canBeHidden: false, searchable: true);
            $table->column(key: 'name', label: __('name'), canBeHidden: false, searchable: true);
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
                        'label' => __('Shippers'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.shippers.index' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.shippers.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                    ]
                )
            ),

        };
    }
}
