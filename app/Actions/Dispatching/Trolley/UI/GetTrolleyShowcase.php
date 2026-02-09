<?php

/*
 * Author: Vika Aqordi
 * Created on 09-02-2026-15h-41m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\Trolley\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTrolleyShowcase
{
    use AsObject;

    public function handle(Trolley $trolley): array
    {
        return [
            'created_at'        => $trolley->created_at,
            'name'              => $trolley->name,
            'status'            => $trolley->status,
            'slug'              => $trolley->slug,
            'delivery_note'     => $trolley->currentDeliveryNote,
        ];
    }
}
