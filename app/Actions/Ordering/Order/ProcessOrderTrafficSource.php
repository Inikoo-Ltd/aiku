<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\CRM\TrafficSource\AttachTrafficSourcesToModel;
use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Actions\CRM\TrafficSource\ProcessTrafficSourceShare;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessOrderTrafficSource implements ShouldBeUnique
{
    use AsAction;

    /**
     * Bounded so a worker that dies without releasing the lock cannot leave an order permanently
     * unattributable.
     */
    public int $jobUniqueFor = 3600;

    public function getJobUniqueId(Order $order): string
    {
        return (string) $order->id;
    }

    /**
     * Attributes the marketing traffic sources responsible for a submitted order.
     *
     * Falls back to the customer's own touch history when the order itself did not
     * capture one, since the checkout flow does not (yet) persist a per-order touch cookie.
     */
    public function handle(Order $order): void
    {
        $rawTouchesData = $order->traffic_sources ?: $order->customer?->traffic_sources;

        if (blank($rawTouchesData)) {
            return;
        }

        $touches = ParseTrafficSourceTouches::run($rawTouchesData);

        if (empty($touches)) {
            return;
        }

        /* Attribute the order under the same model its customer is on, so order-level and
           customer-level numbers cannot silently disagree for anyone moved off the default. */
        $attributionModel = $order->customer?->trafficSources()->first()?->pivot->attribution_model
            ?? ProcessTrafficSourceShare::ATTRIBUTION_LINEAR;

        AttachTrafficSourcesToModel::run($order, $order->shop_id, $touches, $attributionModel);
    }
}
