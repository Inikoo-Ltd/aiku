<?php
/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-11h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Http\Resources\Dispatching\ShipperResource;
use App\Models\Dispatching\Shipper;
use Lorisleiva\Actions\Concerns\AsObject;

class GetShipperShowcase
{
    use AsObject;

    public function handle(Shipper $shipper): array
    {
        return [
                'shipper' => ShipperResource::make($shipper)->getArray()
        ];
    }
}
