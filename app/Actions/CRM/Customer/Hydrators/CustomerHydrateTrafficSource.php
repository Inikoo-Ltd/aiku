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
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateTrafficSource implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {

        $trafficSource = $customer->trafficSource;

        if (!$trafficSource) {
            return;
        }

        $stats['number_customer_purchases'] = $customer->orders()
            ->where('state', '!=', 'creating')
            ->count();

        $stats['total_customer_revenue'] = $customer->orders()
            ->where('state', '!=', 'creating')
            ->sum('total_amount');

        $trafficSource->stats()->update($stats);
    }
}
