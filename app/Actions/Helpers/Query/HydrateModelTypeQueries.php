<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 02:04:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Query;

use App\Actions\Helpers\Query\Hydrators\QueryHydrateCount;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateModelTypeQueries
{
    use AsAction;

    public function handle(string $modelType): void
    {
        QueryHydrateCount::make()->byModelType($modelType);
    }

}
