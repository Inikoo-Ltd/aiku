<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Http\Resources\SupplyChain\SuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class CreateOrgSupplier extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Supplier::class)
            ->leftJoin('supplier_stats', 'supplier_stats.supplier_id', 'suppliers.id')
            ->where('suppliers.group_id', $organisation->group_id)
            ->where('suppliers.status', true)
            ->whereDoesntHave('orgSuppliers', fn ($query) => $query->where('organisation_id', $organisation->id));

        return $queryBuilder
            ->select([
                'suppliers.id',
                'suppliers.slug',
                'suppliers.code',
                'suppliers.name',
                'suppliers.location',
                'suppliers.status',
                'suppliers.agent_id',
                'supplier_stats.number_supplier_products',
                'supplier_stats.number_purchase_orders',
                'supplier_stats.number_stock_deliveries',
            ])
            ->defaultSort('suppliers.code')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withEmptyState([
                    'title' => __('No suppliers to adopt'),
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'add', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return SuppliersResource::collection($suppliers);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Procurement/CreateOrgSupplier',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Add suppliers'),
                'organisation_id' => $this->organisation->id,
                'pageHead' => [
                    'title' => __('Add suppliers'),
                    'icon' => [
                        'icon' => ['fal', 'fa-person-dolly'],
                        'title' => __('Suppliers'),
                    ],
                    'actions' => [
                        Agent::where('organisation_id', $this->organisation->id)->exists() ? [
                            'type' => 'button',
                            'style' => 'create',
                            'tooltip' => __('New supplier'),
                            'label' => __('New supplier'),
                            'route' => [
                                'name' => 'grp.org.procurement.org_suppliers.create_for_agent',
                                'parameters' => [$this->organisation->slug],
                            ],
                        ] : false,
                    ],
                ],
                'data' => SuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexOrgSuppliers::make()->getBreadcrumbs('grp.org.procurement.org_suppliers.index', $routeParameters),
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Add suppliers'),
                    ],
                ],
            ]
        );
    }
}
