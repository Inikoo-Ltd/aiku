<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:34:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ShopPlatformStats;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopPlatformStatsHydrateCustomers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop, Platform $platform): void
    {

        $query = DB::table('customer_sales_channels')
            ->leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
            ->where('customer_sales_channels.shop_id', $shop->id)
            ->where('customer_sales_channels.platform_id', $platform->id)
            ->distinct('customer_id');
        $stats = [
            'number_customers' => $query->count('customer_id')

        ];

        foreach (CustomerStateEnum::cases() as $state) {
            $stats['number_customers_state_' . $state->value] = $query->where('customers.state', $state->value)->count('customer_id');
        }

        $shop->platformStats()->where('platform_id', $platform->id)->update($stats);
    }
}
