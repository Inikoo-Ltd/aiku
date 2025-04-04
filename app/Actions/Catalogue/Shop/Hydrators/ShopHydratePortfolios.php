<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePortfolios implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        if (!($shop->type == ShopTypeEnum::DROPSHIPPING || $shop->type == ShopTypeEnum::FULFILMENT)) {
            return;
        }

        $stats = [
            'number_portfolios'                      => $shop->portfolios()->count(),
            'number_current_portfolios'              => $shop->portfolios()->where('status', true)->count(),
            'number_portfolios_platform_shopify'     => $shop->portfolios()->where('type', PortfolioTypeEnum::SHOPIFY->value)->count(),
            'number_portfolios_platform_woocommerce' => $shop->portfolios()->where('type', PortfolioTypeEnum::WOOCOMMERCE->value)->count(),
        ];


        $shop->dropshippingStats->update($stats);
    }
}
