<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Inventory\OrgStocksWithWrongWeightResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * HELP-4540. SKOs FlagOrgStocksWithWrongWeight found to weigh far from their system weight in
 * the parcels the warehouse weighed. The weight is corrected on the SKO's trade units.
 */
class IndexOrgStocksWithWrongWeight extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation, string $prefix = 'wrong_weights'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $difference = 'abs(org_stocks.measured_weight - coalesce(stocks.gross_weight, 0))';

        return QueryBuilder::for(OrgStock::class)
            ->where('org_stocks.organisation_id', $organisation->id)
            ->where('org_stocks.has_wrong_weight', true)
            ->leftJoin('stocks', 'org_stocks.stock_id', 'stocks.id')
            ->defaultSort('-difference')
            ->select([
                'org_stocks.id',
                'org_stocks.slug',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.measured_weight',
                'org_stocks.measured_weight_parcels',
                'stocks.gross_weight as system_weight',
                DB::raw("org_stocks.measured_weight - coalesce(stocks.gross_weight, 0) as difference"),
            ])
            ->allowedSorts([
                'code',
                'name',
                'measured_weight',
                'measured_weight_parcels',
                AllowedSort::field('system_weight', 'stocks.gross_weight'),
                AllowedSort::callback('difference', fn ($query, bool $descending) => $query->orderByRaw($difference.($descending ? ' desc' : ' asc'))),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('SKO'), __('SKOs')])
                ->defaultSort('-difference')
                ->column(key: 'code', label: __('SKO'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'system_weight', label: __('System weight'), sortable: true, align: 'right')
                ->column(key: 'measured_weight', label: __('Weighed'), sortable: true, align: 'right')
                ->column(key: 'difference', label: __('Difference'), sortable: true, align: 'right')
                ->column(key: 'measured_weight_parcels', label: __('Parcels'), sortable: true, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $orgStocks): AnonymousResourceCollection
    {
        return OrgStocksWithWrongWeightResource::collection($orgStocks);
    }

    public function htmlResponse(LengthAwarePaginator $orgStocks, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/OrgStocksWithWrongWeight',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('Wrong Weights'),
                'pageHead' => [
                    'title'      => __('Wrong Weights'),
                    'icon'       => [
                        'icon'  => ['fal', 'fa-weight-hanging'],
                        'title' => __('Wrong Weights'),
                    ],
                    'afterTitle' => [
                        'label' => __('Weighed parcels of these SKOs do not match their weight. Weigh one and correct its trade units'),
                    ],
                ],
                'orgStocks' => OrgStocksWithWrongWeightResource::collection($orgStocks),
            ]
        )->table($this->tableStructure(prefix: 'wrong_weights'));
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
                            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.wrong_weights.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Wrong Weights'),
                        'icon'  => 'fal fa-weight-hanging',
                    ],
                ],
            ]
        );
    }
}
