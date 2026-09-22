<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-11h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetMasterProductsByCodes extends OrgAction
{
    use WithProductCodesLookup;

    public function handle(MasterProductCategory $masterProductCategory, array $codes): AnonymousResourceCollection
    {
        if ($masterProductCategory->type !== MasterProductCategoryTypeEnum::FAMILY) {
            abort(403, 'Only families keep a product index');
        }

        return $this->lookup(
            MasterAsset::where('master_family_id', $masterProductCategory->id)->orderBy('index_under_master_family'),
            $codes
        );
    }

    public function inMasterShop(MasterShop $masterShop, array $codes): AnonymousResourceCollection
    {
        return $this->lookup(MasterAsset::where('master_shop_id', $masterShop->id)->orderBy('code'), $codes);
    }

    private function lookup(Builder $query, array $codes): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($query->whereIn(\DB::raw('lower(code)'), $codes)->get());
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->requestedCodes());
    }

    public function inMasterShopController(MasterShop $masterShop, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->inMasterShop($masterShop, $this->requestedCodes());
    }
}
