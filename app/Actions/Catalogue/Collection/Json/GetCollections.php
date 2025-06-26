<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetCollections extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, Collection|Shop|ProductCategory $scope, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Collection::class);
        if ($scope instanceof Shop) {
            $queryBuilder->whereNotIn('collections.id', $scope->collections()->pluck('model_id'))
                            ->where('collections.id', '!=', $scope->id);
        } elseif ($scope instanceof ProductCategory) {
            $queryBuilder->whereNotIn('collections.id', $scope->collections()->pluck('collection_id'))
                            ->where('collections.id', '!=', $scope->id);
        } elseif ($scope instanceof Collection) {
            $queryBuilder->whereNotIn('collections.id', $scope->collections()->pluck('collection_id'))
                            ->where('collections.id', '!=', $scope->id);
        }
        $queryBuilder
            ->defaultSort('collections.code')
            ->select([
                'collections.id',
                'collections.code',
                'collections.name',
                'collections.created_at',
                'collections.updated_at',
                'collections.slug',
            ]);

        $queryBuilder->where('collections.shop_id', $parent->id);
        $queryBuilder->leftJoin('shops', 'collections.shop_id', 'shops.id');
        $queryBuilder->addSelect(
            'shops.slug as shop_slug',
            'shops.code as shop_code',
            'shops.name as shop_name',
        );



        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionsResource::collection($collections);
    }

    public function inCollection(Shop $shop, Collection $scope, ActionRequest $request): LengthAwarePaginator
    {
        // dd('xx');
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle(parent: $shop, scope: $scope);
    }

    public function asController(Shop $shop, Shop $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle(parent: $shop, scope: $scope);
    }
    public function inProductCategory(Shop $shop, ProductCategory $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle(parent: $shop, scope: $scope);
    }

}
