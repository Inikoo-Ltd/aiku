<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:14:33 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Http\Resources\Procurement\OrgSuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgSuppliers extends OrgAction
{
    use WithProcurementAuthorisation;
    private Organisation $parent;

    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(OrgSupplier::class)
            ->leftJoin('suppliers', 'org_suppliers.supplier_id', 'suppliers.id')
            ->leftJoin('org_supplier_stats', 'org_supplier_stats.org_supplier_id', 'org_suppliers.id')
            ->where('org_suppliers.organisation_id', $parent->id)
            ->whereNull('org_suppliers.org_agent_id')
            ->where('org_suppliers.status', true);

        $queryBuilder->select([
            'suppliers.code',
            'suppliers.name',
            'suppliers.location',
            'org_supplier_stats.number_org_supplier_products',
            'org_supplier_stats.number_purchase_orders',
            'org_supplier_stats.number_stock_deliveries',
            'org_suppliers.slug as org_supplier_slug',
        ]);

        return $queryBuilder
            ->defaultSort('suppliers.code')
            ->allowedSorts([
                'code',
                'name',
                'location',
                'number_org_supplier_products',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No Suppliers Found'),
                    'count' => $parent->procurementStats->number_active_independent_org_suppliers,
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true)
                ->column(key: 'number_org_supplier_products', label: __("Supplier's Products"), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator|RedirectResponse
    {
        if ($request->input('elements.type') === 'through_agent') {
            return redirect()->route('grp.org.procurement.org_agent_suppliers.index', [
                'organisation' => $organisation->slug,
                'sort'         => 'code',
            ]);
        }

        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya   = true;
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $organisation);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgSuppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Suppliers'),
                'pageHead'    => [
                    'title' => __('Free Suppliers'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-person-dolly'],
                        'title' => __('Free Suppliers'),
                    ],
                    'actions'       => [
                        $this->canEdit && $this->parent instanceof Organisation ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Add suppliers'),
                            'label'   => __('Add suppliers'),
                            'route'   => [
                                'name'       => 'grp.org.procurement.org_suppliers.create',
                                'parameters' => [$this->parent->slug],
                            ],
                        ] : false,
                    ],
                ],
                'data' => OrgSuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return OrgSuppliersResource::collection($suppliers);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Free Suppliers'),
                        'icon'  => 'fal fa-bars',
                        'route' => [
                            'name'       => 'grp.org.procurement.org_suppliers.index',
                            'parameters' => $routeParameters,
                        ],
                    ],
                ],
            ]
        );
    }
}
