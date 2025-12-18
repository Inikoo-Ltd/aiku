<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: UI action to display the list of Returns in warehouse
 */

namespace App\Actions\Dispatching\Return\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Dispatching\Return\ReturnStateEnum;
use App\Http\Resources\Dispatching\OrderReturnsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\OrderReturn;
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

class IndexReturns extends OrgAction
{
    private Warehouse $parent;

    public function handle(Warehouse $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('returns.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrderReturn::class);

        $queryBuilder->where('warehouse_id', $parent->id);

        $queryBuilder->defaultSort('-returns.date')
            ->select([
                'returns.id',
                'returns.slug',
                'returns.reference',
                'returns.state',
                'returns.date',
                'returns.number_items',
                'returns.customer_id',
                'returns.created_at',
            ])
            ->leftJoin('customers', 'returns.customer_id', 'customers.id')
            ->addSelect('customers.name as customer_name', 'customers.slug as customer_slug');

        return $queryBuilder
            ->allowedSorts(['reference', 'date', 'state', 'number_items', 'customer_name'])
            ->allowedFilters([$globalSearch, AllowedFilter::exact('state')])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Warehouse $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No returns found'),
                    'description' => __('Returns will appear here when customers return orders'),
                    'count' => 0,
                ]);

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon', sortable: true);
            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_items', label: __('Items'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("incoming.{$this->warehouse->id}.view");
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function jsonResponse(LengthAwarePaginator $returns): AnonymousResourceCollection
    {
        return OrderReturnsResource::collection($returns);
    }

    public function htmlResponse(LengthAwarePaginator $returns, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Incoming/Returns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('Returns'),
                'pageHead' => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-undo-alt'],
                        'title' => __('Returns'),
                    ],
                    'title' => __('Returns'),
                ],
                'data' => OrderReturnsResource::collection($returns),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.incoming.returns.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Returns'),
                    ]
                ]
            ]
        );
    }
}
