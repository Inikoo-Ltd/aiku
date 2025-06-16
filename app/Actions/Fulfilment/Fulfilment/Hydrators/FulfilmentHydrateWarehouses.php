<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:38:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Models\Fulfilment\Fulfilment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydrateWarehouses implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Fulfilment $fulfilment): string
    {
        return $fulfilment->id;
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $fulfilment->update(
            [
                'number_warehouses' => $fulfilment->warehouses()->count()

            ]
        );
    }


}
