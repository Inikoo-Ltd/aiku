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
        return match ($customerSalesChannels->platform->code) {
            'shopify' => 'https://cdn-icons-png.flaticon.com/64/5968/5968919.png',
            'tiktok' => 'https://cdn-icons-png.flaticon.com/64/3046/3046126.png',
            'woocommerce' => 'https://cdn-icons-png.flaticon.com/512/15466/15466279.png',
            'manual' => 'https://aw.aurora.systems/art/aurora_log_v2_orange.png',
            'ebay' => 'https://cdn-icons-png.flaticon.com/512/888/888848.png',
            'amazon' => 'https://cdn-icons-png.flaticon.com/512/14079/14079391.png',
            'magento' => 'https://cdn-icons-png.flaticon.com/512/825/825535.png',
            default => null,
        };
    }
}
