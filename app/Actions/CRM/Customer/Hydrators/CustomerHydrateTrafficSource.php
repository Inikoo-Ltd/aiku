<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateTrafficSource implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int $customerId): string
    {
        return (string) $customerId;
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $trafficSource = $customer->trafficSource;

        if (!$trafficSource) {
            return;
        }

        $customerStats = $customer->stats;
        $stats['number_customer_purchases'] = $customerStats->number_orders_state_dispatched ?? 0;

        $stats['total_customer_revenue'] = $customerStats->sales_all ?? 0;

        $trafficSource->stats()->update($stats);
    }
}
