<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:13:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZone\Hydrators;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Billables\shippingZone;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShippingZoneHydrateUsageInOrders implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(int $shippingZoneID): string
    {
        return $shippingZoneID;
    }

    public function handle(int $shippingZoneID): void
    {
        $shippingZone = ShippingZone::find($shippingZoneID);
        if (!$shippingZone) {
            return;
        }

        $stats = [
            'number_orders'    => $shippingZone->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])->count(),
            'number_customers' => $shippingZone->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->distinct('orders.customer_id')->count(),
            'last_used_at'     => $shippingZone->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->max('orders.submitted_at'),
            'first_used_at'    => $shippingZone->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->min('orders.submitted_at'),
        ];

        $shippingZone->stats()->update($stats);
    }

}
