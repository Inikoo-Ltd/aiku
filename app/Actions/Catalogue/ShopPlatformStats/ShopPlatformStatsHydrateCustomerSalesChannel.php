<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 18:34:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ShopPlatformStats;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopPlatformStatsHydrateCustomerSalesChannel implements ShouldBeUnique
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
            ->where('shop_id', $shop->id)
            ->where('platform_id', $platform->id);

        $stats = [
            'number_customer_sales_channels' => $query->count(),
            'number_customer_sales_channel_broken' => $query->where('platform_status', false)->count(),
        ];

        $shop->platformStats()->where('platform_id', $platform->id)->update($stats);
    }
}
