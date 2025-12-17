<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 13:40:33 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateCustomerSalesChannels implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $customerId): string
    {
        return $customerId ?? 'empty';
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

        $totalCustomerChannels = $customer->customerSalesChannels()->count();

        $totalDistinctPlatforms = $customer->customerSalesChannels()
            ->distinct('platform_id')
            ->count('platform_id');

        $stats = [
            'number_customer_sales_channels' => $totalCustomerChannels,
            'number_platforms' => $totalDistinctPlatforms,
        ];

        $platformTypeCounts = $customer->customerSalesChannels()
            ->join('platforms', 'customer_sales_channels.platform_id', '=', 'platforms.id')
            ->selectRaw('platforms.type, count(*) as count')
            ->groupBy('platforms.type')
            ->pluck('count', 'type')
            ->toArray();

        foreach (PlatformTypeEnum::cases() as $platformType) {
            $platformTypeName = $platformType->value;
            $stats['number_customer_sales_channels_platform_type_' . $platformTypeName] =
            $platformTypeCounts[$platformTypeName] ?? 0;
        }


        $customer->stats->update($stats);
    }

}
