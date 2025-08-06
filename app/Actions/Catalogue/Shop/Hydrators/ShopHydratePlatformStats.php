<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Feb 2025 13:47:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateCustomers;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateCustomerSalesChannel;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateOrders;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydratePortfolios;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformStats implements ShouldBeUnique
{
    use AsAction;
     use WithHydrateCommand;

    public string $commandSignature = 'hydrate:shop_platform_stats {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = Shop::class;
    }

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {

        if ($shop->platformStats->isEmpty()) {
            return;
        }

        foreach($shop->platformStats as $platformStat) {
            ShopPlatformStatsHydrateCustomers::run($shop, $platformStat->platform);
            ShopPlatformStatsHydratePortfolios::run($shop, $platformStat->platform);
            ShopPlatformStatsHydrateOrders::run($shop, $platformStat->platform);
            ShopPlatformStatsHydrateCustomerSalesChannel::run($shop, $platformStat->platform);
        }
    }


}
