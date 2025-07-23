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

        $stats['number_customer_purchases'] = DB::table('orders')
            ->whereIn('customer_id', $customerIds)
            ->where('state', '!=', 'creating')
            ->select(DB::raw('COUNT(DISTINCT id) as count'))
            ->first()->count ?? 0;

        $stats['total_customer_revenue'] = DB::table('orders')
            ->whereIn('customer_id', $customerIds)
            ->where('state', '!=', 'creating')
            ->select(DB::raw('SUM(total_amount) as total'))
            ->first()->total ?? 0;

        $trafficSource->stats()->update($stats);
    }

    public string $commandSignature = 'xxx22222';

    public function asCommand()
    {
        $product = TrafficSource::find(1592);

        $this->handle($product);
    }
}
