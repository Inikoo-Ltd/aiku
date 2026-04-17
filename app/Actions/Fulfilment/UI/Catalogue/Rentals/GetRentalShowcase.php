<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 23:47:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\UI\Catalogue\Rentals;

use App\Models\Billables\Rental;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRentalShowcase
{
    use AsObject;

    public function handle(Rental $rental): array
    {
        $asset = $rental->asset;

        return [
            'rental'   => JsonResource::make($rental),
            'currency' => $asset->currency,

        ];
    }


}
