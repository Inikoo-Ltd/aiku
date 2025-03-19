<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Services;

use App\Models\Billables\Service;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFulfilmentServiceShowcase
{
    use AsObject;

    public function handle(Service $service)
    {
        return [
            'id'          => $service->id,
            'name'        => $service->name,
            'description' => $service->description,
            'code'        => $service->code,
            'price'       => (float) $service->price,
            'currency'    => $service->currency,
            'unit'        => $service->unit,
            'units'       => (int) $service->units,
            'status'      => $service->status,
            'state'       => $service->state,
            'created_at'  => $service->created_at,
            'is_pallet_handling' => $service->is_pallet_handling
        ];
    }
}
