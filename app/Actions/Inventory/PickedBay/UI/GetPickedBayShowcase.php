<?php

/*
 * Author: Vika Aqordi
 * Created on 09-02-2026-15h-55m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Inventory\PickedBay\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\PickedBay;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPickedBayShowcase
{
    use AsObject;

    public function handle(PickedBay $pickedBay): array
    {
        return [
            'created_at'        => $pickedBay->created_at,
            'name'              => $pickedBay->name,
            'status'            => $pickedBay->status,
            'slug'              => $pickedBay->slug,
            'delivery_note'     => $pickedBay->currentDeliveryNote,
        ];
    }
}
