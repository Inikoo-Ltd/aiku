<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\BrandResource;
use App\Models\Helpers\Brand;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetBrands extends OrgAction
{
    public function handle()
    {
        $queryBuilder = QueryBuilder::for(Brand::class);

        $queryBuilder
            ->leftJoin('media', 'brands.image_id', '=', 'media.id');

        $queryBuilder
            ->defaultSort('brands.id')
            ->select([
                'brands.id',
                'brands.reference',
                'brands.name',
                'brands.slug',
            ]);

        return $queryBuilder->get();
    }

    public function jsonResponse($brands): AnonymousResourceCollection
    {
        return BrandResource::collection($brands);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }

}
