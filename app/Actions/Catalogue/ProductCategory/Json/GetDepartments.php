<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetDepartments extends OrgAction
{
    use WithCatalogueAuthorisation;
    private Shop $parent;

    public function asController(Shop $shop, Collection $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop, scope: $scope);
    }

    public function handle(Shop $parent, Collection $scope, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('product_categories.name', $value)
                    ->orWhereStartWith('product_categories.slug', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(ProductCategory::class);
        $queryBuilder->where('product_categories.shop_id', $parent->id);
        $queryBuilder->whereNotIn('product_categories.id', $scope->parentDepartments()->pluck('model_id'));
        return $queryBuilder
            ->defaultSort('product_categories.code')
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.state',
                'product_categories.description',
                'product_categories.created_at',
                'product_categories.updated_at',
                'product_category_stats.number_current_families',
                'product_category_stats.number_current_products',
            ])
            ->leftJoin('product_category_stats', 'product_categories.id', 'product_category_stats.product_category_id')
            ->where('product_categories.type', ProductCategoryTypeEnum::DEPARTMENT)
            ->allowedSorts(['code', 'name','shop_code','number_current_families','number_current_products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return DepartmentsResource::collection($departments);
    }
}
