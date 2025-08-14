<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:47:26 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\GrpAction;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetMasterProductsNotAttachedToAMasterCollection extends GrpAction
{

    private MasterShop $parent;

    public function handle(MasterShop $parent, MasterCollection $collection, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereStartWith('master_assets.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(MasterAsset::class);
        $queryBuilder->where('master_assets.master_shop_id', $parent->id);

        $queryBuilder->whereNotIn('master_assets.id', $collection->masterProducts()->wherePivot('type', 'direct')->pluck('model_id'));


        $queryBuilder
            ->defaultSort('master_assets.code')
            ->select([
                'master_assets.id',
                'master_assets.code',
                'master_assets.name',
                'master_assets.created_at',
                'master_assets.updated_at',
                'master_assets.slug',
            ])
            ->leftJoin('master_asset_stats', 'master_assets.id', 'master_asset_stats.master_asset_id');


        return $queryBuilder->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($products);
    }

    public function asController(MasterShop $masterShop, MasterCollection $masterCollection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisation($masterShop->group, $request);

        return $this->handle(parent: $masterShop, collection: $masterCollection);
    }



}
