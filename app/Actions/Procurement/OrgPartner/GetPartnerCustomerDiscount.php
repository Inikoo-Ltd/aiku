<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The intercompany customer discount (e.g. everything 45% off) is applied by the seller at
 * invoicing and the imported offer records carry no percentage, so the factor is derived from
 * the customer's recent order history instead.
 */
class GetPartnerCustomerDiscount
{
    use AsObject;

    /**
     * @return float factor to multiply a list price by, e.g. 0.55 for 45% off
     */
    public function handle(Customer $customer): float
    {
        return Cache::remember("partner_customer_discount_factor_$customer->id", now()->addHours(6), function () use ($customer) {
            $factors = $customer->orders()
                ->whereNotIn('state', [OrderStateEnum::CANCELLED, OrderStateEnum::CREATING])
                ->where('gross_amount', '>', 0)
                ->orderByDesc('id')
                ->limit(10)
                ->get(['gross_amount', 'net_amount'])
                ->map(fn ($order) => (float) $order->net_amount / (float) $order->gross_amount)
                ->sort()
                ->values();

            if ($factors->isEmpty()) {
                return 1.0;
            }

            $factor = $factors->get(intdiv($factors->count(), 2));

            return max(0.0, min(1.0, round($factor, 4)));
        });
    }
}
