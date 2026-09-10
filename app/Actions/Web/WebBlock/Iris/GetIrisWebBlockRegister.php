<?php

/*
 * Author Louis Perez
 * Created on 08-09-2026-14h-59m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Traits\WithBlogCategoriesBlock;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockBlogRegister
{
    use AsObject;
    use WithBlogCategoriesBlock;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        return [
            'type'      => $webBlock['type'],
            'structure' => Arr::get(
                $webBlock,
                'web_block.layout.data.fieldValue',
                []
            ),
        ];
    }
}
