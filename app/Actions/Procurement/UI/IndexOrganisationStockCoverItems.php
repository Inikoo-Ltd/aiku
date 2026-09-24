<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Http\Resources\Procurement\StockCoverOrgStocksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrganisationStockCoverItems extends OrgAction
{
    use WithProcurementAuthorisation;

    private ?array $bucketElements = null;

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    protected function getElementGroups(Organisation $organisation): array
    {
        $buckets = GetOrganisationStockCoverBuckets::make();

        return [
            'cover' => [
                'label'    => __('Stock level'),
                'default'  => 'out',
                'elements' => $this->bucketElements ??= collect(GetOrganisationStockCoverBuckets::run($organisation))
                    ->mapWithKeys(fn (array $bucket) => [$bucket['bucket'] => [$bucket['label'], $bucket['count']]])
                    ->all(),
                'engine'   => fn (QueryBuilder $query, array $elements) => $buckets->whereBuckets($query->getEloquentBuilder(), $elements),
            ],
        ];
    }

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        $buckets = GetOrganisationStockCoverBuckets::make();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(OrgStock::class);
        $buckets->scope($queryBuilder->getEloquentBuilder(), $organisation);

        foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'],
                optional: true,
            );
        }

        return $queryBuilder
            ->defaultSort('days_of_cover')
            ->leftJoin('org_stock_families', 'org_stock_families.id', 'org_stocks.org_stock_family_id')
            ->select([
                'org_stocks.id',
                'org_stocks.slug',
                'org_stocks.code',
                'org_stocks.name',
                'org_stock_families.code as family_code',
                'org_stocks.health_rank',
                'org_stocks.quantity_available',
                'org_stock_stats.days_of_cover',
                'org_stock_stats.stock_value',
                'org_stock_stats.on_the_way_po_count',
                'org_stock_stats.recommended_order_quantity',
                'sp.supplier_code',
            ])
            ->selectRaw($buckets->leadTimeExpression().' as lead_time_days')
            ->selectRaw($buckets->bucketExpression().' as bucket')
            ->allowedSorts(['code', 'name', 'family_code', 'health_rank', 'quantity_available', 'days_of_cover', 'lead_time_days', 'supplier_code', 'stock_value'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'],
                );
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('SKO'), __('SKOs')])
                ->defaultSort('days_of_cover')
                ->column(key: 'code', label: __('Reference'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'family_code', label: __('Family'), sortable: true)
                ->column(key: 'health_rank', label: __('Rank'), sortable: true)
                ->column(key: 'bucket_label', label: __('Stock level'))
                ->column(key: 'quantity_available', label: __('Stock'), sortable: true, align: 'right')
                ->column(key: 'days_of_cover', label: __('Days of cover'), sortable: true, align: 'right')
                ->column(key: 'lead_time_days', label: __('Lead time'), sortable: true, align: 'right')
                ->column(key: 'supplier_code', label: __('Supplier'), sortable: true)
                ->column(key: 'recommended_quantity', label: __('Suggested order'), align: 'right')
                ->column(key: 'stock_value', label: __('Stock value'), sortable: true, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $orgStocks, ActionRequest $request): Response
    {
        $warehouse = $this->organisation->warehouses()->first();

        return Inertia::render(
            'Procurement/OrganisationStockCoverItems',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Stock levels'),
                'pageHead'      => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('Stock levels'),
                    ],
                    'title' => __('Stock levels'),
                ],
                'currency'      => $this->organisation->currency->code,
                'warehouseSlug' => $warehouse?->slug,
                'exportRoute'   => [
                    'name'       => 'grp.org.procurement.stock_cover.export',
                    'parameters' => ['organisation' => $this->organisation->slug],
                ],
                'data'          => StockCoverOrgStocksResource::collection($orgStocks),
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.stock_cover.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Stock levels'),
                    ],
                ],
            ]
        );
    }
}
