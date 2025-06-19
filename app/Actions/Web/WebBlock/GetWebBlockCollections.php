<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Http\Resources\Web\WebBlockCollectionResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockCollections
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {

        $collections = DB::table('collections')
            ->leftjoin('model_has_collections', 'model_has_collections.collection_id', '=', 'collections.id')
            ->leftJoin('webpages', function ($join) {
                $join->on('webpages.model_id', '=', 'collections.id');
            })
            ->where('webpages.model_type', 'Collection')
            ->where('model_has_collections.model_id', $webpage->model_id)
            ->where('model_has_collections.model_type', $webpage->model_type)
            ->select(['collections.slug', 'collections.code', 'collections.name', 'collections.image_id', 'webpages.url as url'])
            ->whereNull('collections.deleted_at')
            ->get();

        $permissions = ['hidden'];
        

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['collection']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.collections', WebBlockCollectionResource::collection($collections)->toArray(request()));

        return $webBlock;
    }

}
