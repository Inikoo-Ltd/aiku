<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\TrafficSource\Hydrator;

use App\Actions\Traits\WithEnumStats;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\TrafficSource;

class TrafficSourceHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TrafficSource $trafficSource): string
    {
        return $trafficSource->id;
    }

    public function handle(TrafficSource $trafficSource): void
    {
        $customers = $trafficSource->customers;

        $stats = [
            'number_customers' => $customers->unique('id')->count(),
        ];

        $customerIds = $customers->pluck('id')->unique();

        $stats['number_customer_purchases'] =  DB::table('customer_stats')
            ->whereIn('customer_id', $customerIds)
            ->select(DB::raw('SUM(number_orders_state_dispatched) as total'))
            ->first()->total ?? 0;

        $stats['total_customer_revenue'] = DB::table('customer_stats')
            ->whereIn('customer_id', $customerIds)
            ->select(DB::raw('SUM(sales_all) as total'))
            ->first()->total ?? 0;

        $trafficSource->stats()->update($stats);
    }
}
