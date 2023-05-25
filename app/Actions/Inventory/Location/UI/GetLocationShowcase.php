<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:30:45 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Inventory\Location\UI;

use App\Models\Inventory\Location;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLocationShowcase
{
    use AsObject;

    public function handle(Location $location): array
    {
        return [
            [

            ]
        ];
    }
}
