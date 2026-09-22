<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-11h-43m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Json;

use App\Actions\Masters\MasterAsset\Json\WithProductCodesLookup;
use App\Actions\OrgAction;
use App\Http\Resources\Masters\RelatedMasterProductsCategoriesResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetMasterProductCategoriesByCodes extends OrgAction
{
    use WithProductCodesLookup;

    public function handle(MasterShop $masterShop, array $codes): AnonymousResourceCollection
    {
        $categories = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->whereIn(\DB::raw('lower(code)'), $codes)
            ->orderBy('code')
            ->get();

        return RelatedMasterProductsCategoriesResource::collection($categories);
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterShop, $this->requestedCodes());
    }
}
