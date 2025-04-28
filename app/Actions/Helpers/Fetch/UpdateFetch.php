<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Nov 2023 12:56:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Fetch;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Transfers\Fetch;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateFetch
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;


    public function handle(Fetch $fetch, array $modelData): Fetch
    {
        return $this->update($fetch, $modelData);
    }


}
