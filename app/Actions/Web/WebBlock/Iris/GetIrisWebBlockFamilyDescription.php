<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockFamilyDescription
{
    use AsObject;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $this->setWebBlockLayoutData($webpage, $webBlock, 'family_description');

        data_set($webBlock, 'web_block.layout.data.fieldValue.family', WebBlockFamilyResource::make($webpage->model)->toArray(request()));

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
