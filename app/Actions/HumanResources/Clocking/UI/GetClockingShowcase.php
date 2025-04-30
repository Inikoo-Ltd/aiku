<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:30:45 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\Clocking\UI;

use App\Http\Resources\HumanResources\ClockingResource;
use App\Models\HumanResources\Clocking;
use Lorisleiva\Actions\Concerns\AsObject;

class GetClockingShowcase
{
    use AsObject;

    public function handle(Clocking $clocking): array
    {
        return ClockingResource::make($clocking)->getArray();
    }
}
