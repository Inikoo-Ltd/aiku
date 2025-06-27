<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydratePlatforms implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {
        $totalPlatforms = $customer->customerSalesChannels()->count();

        $stats = [
            'number_platforms' => $totalPlatforms,
        ];

        $platformTypeCounts = $customer->customerSalesChannels()
            ->join('platforms', 'customer_sales_channels.platform_id', '=', 'platforms.id')
            ->selectRaw('platforms.type, count(*) as count')
            ->groupBy('platforms.type')
            ->pluck('count', 'type')
            ->toArray();

        foreach (PlatformTypeEnum::cases() as $platformType) {
            $platformTypeName = $platformType->value;
            $stats['number_platforms_type_' . $platformTypeName] =
            $platformTypeCounts[$platformTypeName] ?? 0;
        }


        $customer->stats->update($stats);
    }

}
