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
use App\Http\Resources\Catalogue\BeefreeProductResource;
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
        $collectionId = $searchData['collection_id'] ?? null;
        $familyId = $searchData['family_id'] ?? null;
        $subDepartmentId = $searchData['sub_department_id'] ?? null;

        // Determine frequency based on time_filter
        $frequency = match ($timeFilter) {
            'month' => TimeSeriesFrequencyEnum::MONTHLY->value,
            'year' => TimeSeriesFrequencyEnum::YEARLY->value,
            default => TimeSeriesFrequencyEnum::WEEKLY->value,
        };

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('products.code', 'like', '%' . $value . '%')
                    ->orWhere('products.name', 'like', '%' . $value . '%');
            });
        });

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->where('products.shop_id', $parent->id);
        $queryBuilder->where('products.is_for_sale', true);

        // Apply collection filter if provided
        if ($collectionId && $tabType === 'collection_family') {
            $queryBuilder->join('collection_has_models', function ($join) {
                $join->on('products.id', '=', 'collection_has_models.model_id')
                    ->where('collection_has_models.model_type', '=', 'Product');
            });
            $queryBuilder->where('collection_has_models.collection_id', '=', $collectionId);
        }

        // Apply family filter if provided
        if ($familyId && $tabType === 'collection_family') {
            $queryBuilder->where('products.family_id', '=', $familyId);
        }

        // Apply sub-department filter if provided
        if ($subDepartmentId && $tabType === 'collection_family') {
            $queryBuilder->where('products.sub_department_id', '=', $subDepartmentId);
        }

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
                frequency: $frequency,
                prefix: 'sales',
                includeLY: false,
                localKey: 'asset_id',
                timeSeriesFilters: ['shop_id' => $parent->id],
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


    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return BeefreeProductResource::collection($products);
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        $searchData = [
            'search' => $request->input('search', ''),
            'tab_type' => $request->input('tab_type', 'products'),
            'time_filter' => $request->input('time_filter'),
            'collection_id' => $request->input('collection_id'),
            'family_id' => $request->input('family_id'),
            'sub_department_id' => $request->input('sub_department_id'),
        ];

        return $this->handle(parent: $shop, searchData: $searchData);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tab_type' => ['nullable', 'string', 'in:products,new_in,trending,collection_family'],
            'time_filter' => ['nullable', 'string', 'in:week,month,year'],
            'collection_id' => ['nullable', 'integer', 'exists:collections,id'],
            'family_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sub_department_id' => ['nullable', 'integer', 'exists:product_categories,id'],
        ];
    }
}
