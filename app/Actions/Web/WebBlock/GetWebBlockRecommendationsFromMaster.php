<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock;


use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Catalogue\ProductsWebpageResource;

class GetWebBlockRecommendationsFromMaster
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions =  ['edit', 'hidden'];


        $webBlockType = data_get($webBlock, 'type', '');
        $webPublishedLayout = $webpage->website->published_layout;


        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
      
        data_set( $webBlock,'web_block.layout.data.fieldValue.products_recommended',ProductsWebpageResource::collection($webpage->model->relatedProducts)->toArray(request()));
        return $webBlock;
    }
}
