<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Nov 2023 12:55:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Fetch;

use App\Models\Transfers\Fetch;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreFetch
{
    use AsAction;
    use WithAttributes;


    public function handle(array $modelData): Fetch
    {
        return Fetch::create($modelData);
    }

}
