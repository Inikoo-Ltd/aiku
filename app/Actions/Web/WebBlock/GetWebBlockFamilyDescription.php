<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock;

use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockFamilyDescription
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions =  ['edit','hidden'];


        $webBlockType = data_get($webBlock, 'type', '');
        $webPublishedLayout = $webpage->website->published_layout;

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', data_get($webPublishedLayout, "$webBlockType.data.fieldValue", []));
        data_set($webBlock, 'web_block.layout.data.fieldValue.family', WebBlockFamilyResource::make($webpage->model)->toArray(request()));
        return $webBlock;
    }

}
