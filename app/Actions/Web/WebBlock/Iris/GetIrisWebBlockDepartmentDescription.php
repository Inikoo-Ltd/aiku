<?php

/*
 * author Louis Perez
 * created on 09-06-2026-14h-07m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Http\Resources\Web\WebBlockSubDepartmentsResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockDepartmentDescription
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions =  ['edit','hidden'];


        $webBlockType = data_get($webBlock, 'type', '');
        $webPublishedLayout = $webpage->website->published_layout;

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', data_get($webPublishedLayout, "department_description.$webBlockType.fieldValue", []));
        data_set($webBlock, 'web_block.layout.data.fieldValue.id', data_get($webBlock, 'type'));
        data_set($webBlock, 'web_block.layout.data.fieldValue.department', WebBlockSubDepartmentsResource::make($webpage->model)->resolve());


        return [
            'type' => $webBlock['type'],
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }

}
