<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 16:58:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Models\Dropshipping\CustomerSalesChannel;

trait GetPlatformLogo
{
    public function getPlatformLogo(CustomerSalesChannel $customerSalesChannels): ?string
    {
        $logo_img = null;
        if ($customerSalesChannels->platform->code === 'shopify') {
            $logo_img = 'https://cdn-icons-png.flaticon.com/64/5968/5968919.png';
        } elseif ($customerSalesChannels->platform->code === 'tiktok') {
            $logo_img = 'https://cdn-icons-png.flaticon.com/64/3046/3046126.png';
        } elseif ($customerSalesChannels->platform->code === 'woocommerce') {
            $logo_img = 'https://cdn-icons-png.flaticon.com/64/825/825519.png';
        } elseif ($customerSalesChannels->platform->code === 'manual') {
            $logo_img = 'https://aw.aurora.systems/art/aurora_log_v2_orange.png';
        }

        return $logo_img;
    }
}
