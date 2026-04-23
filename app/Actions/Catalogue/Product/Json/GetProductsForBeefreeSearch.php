<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Tue, 21 Apr 2026
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Catalogue\ProductsWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetProductsForBeefreeSearch extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, array $searchData, $prefix = null): LengthAwarePaginator
    {
        $tabType = $searchData['tab_type'] ?? 'products';
        $timeFilter = $searchData['time_filter'] ?? null;

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($tabType) {
            $query->where(function ($query) use ($value, $tabType) {
                if ($tabType === 'new_in') {
                    // New In tab: search only by name, not code/SKU
                    $query->where('products.name', 'ilike', '%' . $value . '%');
                } else {
                    // Other tabs: search by both name and code
                    $query->where('products.code', 'ilike', '%' . $value . '%')
                        ->orWhere('products.name', 'ilike', '%' . $value . '%');
                }
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->id);
        $queryBuilder->where('products.is_for_sale', true);

        // Base selects
        $selects = [
            'products.*',
        ];

        // Handle different tab types
        if ($tabType === 'trending') {
            // Trending: Add time-series data for sales sorting
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'asset_time_series',
                timeSeriesRecordsTable: 'asset_time_series_records',
                foreignKey: 'asset_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: 'sales',
                includeLY: false,
                localKey: 'asset_id',
                timeSeriesFilters: ['shop_id' => $parent->id] + $this->getTimeFilterConditions($timeFilter),
            );

            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external'];
            $queryBuilder->defaultSort('-sales_grp_currency_external');
        } elseif ($tabType === 'new_in') {
            // New In: Sort by creation date
            $queryBuilder->defaultSort('-created_at');
        } else {
            // Default products tab
            $queryBuilder->defaultSort('-id');
        }

        // Apply search if provided
        if (!empty($searchData['search'])) {
            $searchValue = $searchData['search'];
            $queryBuilder->where(function ($query) use ($searchValue, $tabType) {
                if ($tabType === 'new_in') {
                    // New In tab: search only by name
                    $query->where('products.name', 'like', '%' . $searchValue . '%');
                } else {
                    // Other tabs: search by both name and code
                    $query->where('products.code', 'like', '%' . $searchValue . '%')
                        ->orWhere('products.name', 'like', '%' . $searchValue . '%');
                }
            });
        }

        return $queryBuilder
            ->select($selects)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, queryName: 'per_page')
            ->withQueryString();
    }

    private function getTimeFilterConditions(?string $timeFilter): array
    {
        if (!$timeFilter) {
            return [];
        }

        $now = now();
        $conditions = [];

        switch ($timeFilter) {
            case 'week':
                $startDate = $now->copy()->subDays(7)->format('Y-m-d');
                break;
            case 'month':
                $startDate = $now->copy()->subDays(30)->format('Y-m-d');
                break;
            case 'year':
                $startDate = $now->copy()->subDays(365)->format('Y-m-d');
                break;
            default:
                return [];
        }

        $conditions['date_from'] = $startDate;
        $conditions['date_to'] = $now->format('Y-m-d');

        return $conditions;
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsWebpageResource::collection($products);
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        $searchData = [
            'search' => $request->input('search', ''),
            'tab_type' => $request->input('tab_type', 'products'),
            'time_filter' => $request->input('time_filter'),
        ];

        return $this->handle(parent: $shop, searchData: $searchData);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tab_type' => ['nullable', 'string', 'in:products,new_in,trending'],
            'time_filter' => ['nullable', 'string', 'in:week,month,year'],
        ];
    }
}
