<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 May 2025 19:56:14 Central Indonesia Time, Sanur, Plane KL-Shanghai
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\WithCollectionSubNavigation;
use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Catalogue\CollectionTabsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductsInCollection extends OrgAction
{
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithCollectionSubNavigation;
    use WithCatalogueAuthorisation;


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

        $isSales = $prefix === CollectionTabsEnum::PRODUCT_SALES->value;

        $queryBuilder = QueryBuilder::for(Product::class);
        if (!$isSales) {
            $queryBuilder->orderBy('products.state');
        }
        $queryBuilder->whereNull('products.exclusive_for_customer_id');

        $queryBuilder->join('collection_has_models', function ($join) {
            $join->on('products.id', '=', 'collection_has_models.model_id')
                ->where('collection_has_models.model_type', '=', 'Product');
        });
        $queryBuilder->where('collection_has_models.collection_id', '=', $collection->id);

        $selects = [
            'products.id',
            'products.code',
            'products.name',
            'products.state',
            'products.price',
            'products.rrp',
            'products.created_at',
            'products.updated_at',
            'products.slug',
            'products.is_for_sale',
            'products.web_images',
            'available_quantity',
            'units',
            'unit',
            'master_product_id',
        ];

        $allowedSorts = ['code', 'name'];

        if ($isSales) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'asset_time_series',
                timeSeriesRecordsTable: 'asset_time_series_records',
                foreignKey: 'asset_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'invoices'                    => 'invoices',
                    'refunds'                     => 'refunds',
                    'sold'                        => 'sold',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix,
                includeLY: true,
                localKey: 'asset_id',
                timeSeriesFilters: ['shop_id' => $collection->shop_id],
            );

            $selects = array_merge($selects, [
                DB::raw("'".group()->currency->code."' as grp_currency_code"),
                $timeSeriesData['selectRaw']['sales_grp_currency_external'],
                $timeSeriesData['selectRaw']['invoices'],
                $timeSeriesData['selectRaw']['refunds'],
                $timeSeriesData['selectRaw']['sold'],
                $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'],
            ]);

            $allowedSorts = array_merge($allowedSorts, ['sales_grp_currency_external', 'invoices', 'refunds', 'sold']);
        }

        $queryBuilder
            ->defaultSort($isSales ? '-sales_grp_currency_external' : 'products.code')
            ->select($selects)
            ->leftJoin('product_stats', 'products.id', 'product_stats.product_id');

        return $queryBuilder->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Collection $collection, $prefix = null, $action = true): Closure
    {
        return function (InertiaTable $table) use ($collection, $prefix, $action) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            if ($prefix === CollectionTabsEnum::PRODUCT_SALES->value) {
                $table->betweenDates(['date']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("Collection doesn't have any products"),
                        'count' => $collection->stats->number_families,
                    ]
                );

            if ($prefix === CollectionTabsEnum::PRODUCT_SALES->value) {
                $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'invoices', label: __('Invoices'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'refunds', label: __('Refunds'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'sold', label: __('Sold'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'sales_grp_currency_external', label: __('Sales'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'sales_grp_currency_external_delta', label: __('Δ 1Y'), canBeHidden: false, align: 'right');

                return;
            }

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'image_thumbnail', label: '', type: 'avatar')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            // Only a collection that does not follow its master items is edited in the shop
            if ($action && $collection->master_collection_id && $collection->not_follow_master_items) {
                $table->column(key: 'actions', label: __('Action'), canBeHidden: false);
            }
        };
    }


}
