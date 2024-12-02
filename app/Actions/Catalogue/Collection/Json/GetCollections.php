<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasCatalogueAuthorisation;
use App\Http\Resources\Catalogue\CollectionResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetCollections extends OrgAction
{
    use HasCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Shop $parent, Collection $scope, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Collection::class);
        $queryBuilder->whereNotIn('collections.id', $scope->collections()->pluck('model_id'))
                        ->where('collections.id', '!=', $scope->id);

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
        return CollectionResource::collection($collections);
    }

    public function asController(Shop $shop, Collection $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle(parent: $shop, scope: $scope);
    }

}
