<?php

namespace App\Actions\Procurement\PurchaseOrder;

use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class ResolvePurchaseOrderDeliveryAddress
{
    use AsObject;

    public function handle(Organisation $organisation, ?string $deliveryAddress = null): ?string
    {
        if (filled($deliveryAddress)) {
            return $deliveryAddress;
        }

        return $organisation->warehouses()
            ->with('address')
            ->oldest('id')
            ->first()
            ?->address
            ?->formatted_address;
    }
}
