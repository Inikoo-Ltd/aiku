<?php

/*
 * author Arya Permana - Kirin
 * created on 16-06-2025-14h-55m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Collection\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetCollectionsForWorkshop extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(ProductCategory $scope, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->join('model_has_collections', function ($join) use ($scope) {
            $join->on('model_has_collections.collection_id', '=', 'collections.id')
                ->where('model_has_collections.model_type', '=', 'ProductCategory')
                ->where('model_has_collections.model_id', '=', $scope->id);
        });
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

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($productCategory->shop, $request);
        return $this->handle($productCategory);
    }

}
