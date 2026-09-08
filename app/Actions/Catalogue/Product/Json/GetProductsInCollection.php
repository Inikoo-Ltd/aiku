<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\ProductsTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsInCollection extends OrgAction
{
    protected function getElementGroups(Collection $collection, $bucket = null): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ProductStateEnum::labels($bucket),
                    ProductStateEnum::count($collection, $bucket)
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('products.state', $elements);
                }
            ],
        ];
    }

    public function handle(Collection $collection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->orderBy('products.state');
        $queryBuilder->leftJoin('shops', 'products.shop_id', 'shops.id');
        $queryBuilder->leftJoin('organisations', 'products.organisation_id', '=', 'organisations.id');
        $queryBuilder->where('products.is_main', true);

        $queryBuilder->join('collection_has_models', function ($join) use ($collection) {
            $join->on('products.id', '=', 'collection_has_models.model_id')
                ->where('collection_has_models.model_type', '=', 'Product')
                ->where('collection_has_models.collection_id', '=', $collection->id);
        });


        foreach ($this->getElementGroups($collection) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }



        $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
            timeSeriesTable: 'asset_time_series',
            timeSeriesRecordsTable: 'asset_time_series_records',
            foreignKey: 'asset_id',
            aggregateColumns: [
                'invoices'           => 'invoices_all',
                'sales_external'     => 'sales_all',
                'customers_invoiced' => 'customers_invoiced_all',
            ],
            frequency: TimeSeriesFrequencyEnum::DAILY->value,
            prefix: $prefix,
            includeLY: false,
            localKey: 'asset_id',
        );

        $queryBuilder
            ->defaultSort('products.code')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.state',
                'products.price',
                'products.created_at',
                'products.updated_at',
                'products.slug',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                $timeSeriesData['selectRaw']['invoices_all'],
                $timeSeriesData['selectRaw']['sales_all'],
                $timeSeriesData['selectRaw']['customers_invoiced_all']
            ])
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts(['code', 'name', 'shop_slug', 'department_slug', 'family_slug'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function asController(Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($collection->shop, $request)->withTab(ProductsTabsEnum::values());

        return $this->handle(collection: $collection);
    }
}
