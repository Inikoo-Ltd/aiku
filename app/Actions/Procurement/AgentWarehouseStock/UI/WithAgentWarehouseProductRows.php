<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-15 15:07
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock\UI;

use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Models\Helpers\Media;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Product lines of an agent's goods on their way through it, from being made by the supplier to sitting in its
 * warehouse or in a container that has not left. Each row is one product for one organisation, counted in units.
 */
trait WithAgentWarehouseProductRows
{
    /**
     * Joins what every row shows to a query with organisation_id, supplier_product_id and purchase_order_id columns
     * on the given table.
     */
    protected function joinProductRowDetails(Builder $query, string $table): Builder
    {
        return $query
            ->join('organisations', 'organisations.id', "$table.organisation_id")
            ->join('supplier_products', 'supplier_products.id', "$table.supplier_product_id")
            ->leftJoin('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', "$table.purchase_order_id")
            ->addSelect([
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.units_per_carton',
                'suppliers.code as supplier_code',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
                'purchase_orders.reference as purchase_order_reference',
                'purchase_orders.slug as purchase_order_slug',
                DB::raw(OrgStock::unitsPerSkoSql("$table.organisation_id", "$table.supplier_product_id").' as units_per_sko'),
                DB::raw("(select slug from org_supplier_products where supplier_product_id = $table.supplier_product_id and organisation_id = $table.organisation_id order by id limit 1) as org_supplier_product_slug"),
                DB::raw("(select slug from org_suppliers where supplier_id = supplier_products.supplier_id and organisation_id = $table.organisation_id order by id limit 1) as org_supplier_slug"),
                DB::raw($this->productImageIdSql().' as image_id'),
            ]);
    }

    /**
     * The supplier product's own picture, or the first picture of its trade units; needs supplier_products joined.
     */
    protected function productImageIdSql(): string
    {
        return "coalesce(supplier_products.image_id, (select trade_units.image_id from model_has_trade_units join trade_units on trade_units.id = model_has_trade_units.trade_unit_id where model_has_trade_units.model_type = 'SupplierProduct' and model_has_trade_units.model_id = supplier_products.id and trade_units.image_id is not null order by model_has_trade_units.id limit 1))";
    }

    protected function productRowFilters(): array
    {
        return [
            AllowedFilter::callback('global', function ($query, $value) {
                $like = '%'.addcslashes((string) $value, '%_\\').'%';
                $query->where(fn ($query) => $query
                    ->whereRaw('supplier_products.code collate "C" ilike ?', [$like])
                    ->orWhere('supplier_products.name', 'ilike', $like)
                    ->orWhere('suppliers.code', 'ilike', $like)
                    ->orWhere('purchase_orders.reference', 'ilike', $like));
            }),
            AllowedFilter::callback('organisation', fn ($query, $value) => $query->where('organisations.slug', $value)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productRow(object $row): array
    {
        $unitsPerSko          = (float) ($row->units_per_sko ?: 1);
        $linksIntoProcurement = !($this->hideProcurementDetails ?? false);

        return [
            'organisation_code'         => $row->organisation_code,
            'code'                      => $row->code,
            'name'                      => $row->name,
            'supplier_code'             => $row->supplier_code,
            'org_supplier_product_slug' => $row->org_supplier_product_slug,
            'org_supplier_slug'         => $linksIntoProcurement ? $row->org_supplier_slug : null,
            'purchase_order_reference'  => $row->purchase_order_reference,
            'purchase_order_slug'       => $linksIntoProcurement ? $row->purchase_order_slug : null,
            'quantity'                  => (float) $row->quantity,
            'units_per_sko'             => $unitsPerSko,
            'skos'                      => round((float) $row->quantity / $unitsPerSko, 3),
            'image_id'                  => $row->image_id,
        ];
    }

    /**
     * The picture of each product on the page, from one query for the whole page.
     */
    protected function withProductImages(LengthAwarePaginator $rows): LengthAwarePaginator
    {
        $images = Media::whereIn('id', $rows->getCollection()->pluck('image_id')->filter()->unique())->get()->keyBy('id');

        $rows->getCollection()->transform(function (array $row) use ($images) {
            $image = $images->get(Arr::pull($row, 'image_id'));

            return array_merge($row, [
                'image_thumbnail' => $image ? GetPictureSources::run($image->getImage()->resize(80, 80)) : null,
                'image_full'      => $image?->fullResolutionUrls(),
            ]);
        });

        return $rows;
    }

    /**
     * @param  array<int, array{label: string, value: string, count: int, tooltip: string}>  $organisationOptions
     */
    protected function productRowsResponse(LengthAwarePaginator $rows, ActionRequest $request, string $title, string $icon, string $routeName, string $kind, array $organisationOptions, array $extraFilterOptions = []): Response
    {
        return Inertia::render(
            'Org/Procurement/AgentWarehouseProducts',
            [
                'title'         => $title,
                'kind'          => $kind,
                'breadcrumbs'   => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Warehouse'),
                                'route' => [
                                    'name'       => 'grp.org.agent.warehouse.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => $title,
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'      => [
                    'model' => __('Warehouse'),
                    'icon'  => ['title' => $title, 'icon' => $icon],
                    'title' => $title,
                ],
                'data'          => $rows,
                'filterOptions' => array_merge(['organisation' => $organisationOptions], $extraFilterOptions),
            ]
        );
    }

    /**
     * @return array<int, array{label: string, value: string, count: int, tooltip: string}>
     */
    protected function organisationOptions(Collection $counts): array
    {
        return $counts
            ->sortBy('code')
            ->map(fn ($organisation) => ['label' => strtoupper($organisation->code), 'value' => $organisation->slug, 'count' => (int) $organisation->total, 'tooltip' => $organisation->country_name ? "{$organisation->name} ({$organisation->country_name})" : $organisation->name])
            ->values()
            ->all();
    }
}
