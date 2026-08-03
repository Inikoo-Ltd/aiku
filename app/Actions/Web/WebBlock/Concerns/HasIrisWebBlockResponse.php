<?php

/*
 * author Louis Perez
 * created on 21-06-2026-01h-28m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use Illuminate\Support\Arr;

trait HasIrisWebBlockResponse
{
    protected function irisResponse(array $webBlock): array
    {
        return [
            'type' => data_get($webBlock, 'type'),
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }
}
