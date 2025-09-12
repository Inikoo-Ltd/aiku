<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\UI;

use App\Http\Resources\Catalogue\ChargeResource;
use App\Models\Billables\Charge;
use Lorisleiva\Actions\Concerns\AsObject;

class GetChargeShowcase
{
    use AsObject;

    public function handle(Charge $charge): array
    {
        return [
           'charge' => ChargeResource::make($charge)->resolve()
        ];
    }
}
