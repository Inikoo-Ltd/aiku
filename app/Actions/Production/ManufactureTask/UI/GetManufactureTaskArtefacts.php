<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Models\Production\ManufactureTask;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetManufactureTaskArtefacts
{
    use AsObject;

    public function handle(ManufactureTask $manufactureTask, ActionRequest $request): Collection
    {
        return $manufactureTask->artefacts()->get();
    }
}
