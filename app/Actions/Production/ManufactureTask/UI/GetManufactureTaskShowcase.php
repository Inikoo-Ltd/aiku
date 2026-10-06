<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Models\Production\ManufactureTask;
use Lorisleiva\Actions\Concerns\AsObject;

class GetManufactureTaskShowcase
{
    use AsObject;

    public function handle(ManufactureTask $manufactureTask): array
    {
        return [
            'slug'          => $manufactureTask->slug,
            'code'          => $manufactureTask->code,
            'name'          => $manufactureTask->name,
            'description'   => $manufactureTask->description,
            'status'        => $manufactureTask->status,
            'is_piece_rate' => $manufactureTask->is_piece_rate,
        ];
    }
}
