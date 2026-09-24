<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockRegisterDashboard
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
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
