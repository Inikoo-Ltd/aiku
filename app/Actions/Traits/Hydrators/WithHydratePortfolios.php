<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 20:02:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithHydratePortfolios
{
    public function getPortfoliosStats(Group|Organisation|Shop $model): array
    {
        $shopifyPlatform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->first();
        $wooCommercePlatform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->first();

        return [
            'number_portfolios'                      => $model->portfolios()->count(),
            'number_current_portfolios'              => $model->portfolios()->where('status', true)->count(),
            'number_portfolios_platform_shopify'     => $model->portfolios()->where('platform_id', $shopifyPlatform->id)->count(),
            'number_portfolios_platform_woocommerce' => $model->portfolios()->where('platform_id', $wooCommercePlatform->id)->count(),
        ];
    }
}
