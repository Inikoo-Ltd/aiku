<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:24:56 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Ordering\Platform;

use App\Enums\EnumHelperTrait;

enum PlatformTypeEnum: string
{
    use EnumHelperTrait;

    case SHOPIFY = 'shopify';
    case TIKTOK = 'tiktok';
    case WOOCOMMERCE = 'woocommerce';
    case EBAY = 'ebay';
    case MANUAL = 'manual';

    public function labels(): array
    {
        return [
            'shopify'     => 'Shopify',
            'tiktok'      => 'Tiktok',
            'woocommerce' => 'Woo Commerce',
            'ebay'        => 'Ebay',
            'manual'        => __('Manual'),
        ];
    }
}
