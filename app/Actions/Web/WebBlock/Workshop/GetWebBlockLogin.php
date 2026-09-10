<?php

/*
 * Author Louis Perez
 * Created on 08-09-2026-14h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockLogin
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        return $webBlock;
    }
}
