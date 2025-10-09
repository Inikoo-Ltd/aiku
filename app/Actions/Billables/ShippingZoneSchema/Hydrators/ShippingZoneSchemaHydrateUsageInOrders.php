<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 15:54:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema\Hydrators;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShippingZoneSchemaHydrateUsageInOrders implements ShouldBeUnique
{
    use AsAction;


    public function getJobUniqueId(int $shippingZoneSchemaID): string
    {
        return $shippingZoneSchemaID;
    }

    public function handle(int $shippingZoneSchemaID): void
    {
        $shippingZoneSchema = ShippingZoneSchema::find($shippingZoneSchemaID);
        if (!$shippingZoneSchema) {
            return;
        }

        $stats = [
            'number_orders'    => $shippingZoneSchema->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])->count(),
            'number_customers' => $shippingZoneSchema->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->distinct('orders.customer_id')->count(),
            'last_used_at'     => $shippingZoneSchema->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->max('orders.submitted_at'),
            'first_used_at'    => $shippingZoneSchema->orders()
                ->whereNotIn('state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->min('orders.submitted_at'),
        ];

        $shippingZoneSchema->stats()->update($stats);
    }

}
