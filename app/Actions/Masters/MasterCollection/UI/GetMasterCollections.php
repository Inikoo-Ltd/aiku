<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\GrpAction;
use App\Http\Resources\Masters\MasterCollectionsResource;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetMasterCollections extends GrpAction
{

    private MasterShop $parent;

    public function handle(MasterShop $parent, MasterCollection|MasterShop|MasterProductCategory $scope, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(MasterCollection::class);
        if ($scope instanceof MasterShop) {
            $queryBuilder->whereNotIn('master_collections.id', $scope->masterCollections()->pluck('model_id'))
                            ->where('master_collections.id', '!=', $scope->id);
        } elseif ($scope instanceof MasterProductCategory) {
            $queryBuilder->whereNotIn('master_collections.id', $scope->masterCollections()->pluck('master_collection_id'))
                            ->where('master_collections.id', '!=', $scope->id);
        } elseif ($scope instanceof MasterCollection) {
            $queryBuilder->whereNotIn('master_collections.id', $scope->masterCollections()->pluck('master_collection_id'))
                            ->where('master_collections.id', '!=', $scope->id);
        }
        $queryBuilder
            ->defaultSort('master_collections.code')
            ->select([
                'master_collections.id',
                'master_collections.code',
                'master_collections.name',
                'master_collections.created_at',
                'master_collections.updated_at',
                'master_collections.slug',
            ]);

        $queryBuilder->where('master_collections.master_shop_id', $parent->id);
        $queryBuilder->leftJoin('master_shops', 'master_collections.master_shop_id', 'master_shops.id');
        $queryBuilder->addSelect(
            'master_shops.slug as master_shop_slug',
            'master_shops.code as master_shop_code',
            'master_shops.name as master_shop_name',
        );



        return $queryBuilder
            ->allowedSorts(['code', 'name'])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return MasterCollectionsResource::collection($collections);
    }

    public function inMasterCollection(MasterShop $masterShop, MasterCollection $scope, ActionRequest $request): LengthAwarePaginator
    {
        // dd('xx');
        $this->parent = $masterShop;
        $this->initialisation($masterShop->group, $request);
        return $this->handle(parent: $masterShop, scope: $scope);
    }

    public function asController(MasterShop $masterShop, MasterShop $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisation($masterShop->group, $request);
        return $this->handle(parent: $masterShop, scope: $scope);
    }
    public function inMasterProductCategory(MasterShop $masterShop, MasterProductCategory $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisation($masterShop->group, $request);
        return $this->handle(parent: $masterShop, scope: $scope);
    }

}
