<?php

/*
 * author Arya Permana - Kirin
 * created on 03-07-2025-11h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\WebBlock;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockBlog
{
    use AsObject;


    public function handle(Webpage $webpage, array $webBlock): array
    {

        $permissions = ['edit'];
        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        return $webBlock;
    }
}
