<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Brand\Hydrators;

use App\Models\Helpers\Brand;
use Lorisleiva\Actions\Concerns\AsAction;

class BrandHydrateModels
{
    use AsAction;

    public function handle(Brand $brand): void
    {
        $stats = [
            'number_models' => $brand->tradeUnits()->count(),
        ];

        $brand->updateQuietly($stats);
    }


}
