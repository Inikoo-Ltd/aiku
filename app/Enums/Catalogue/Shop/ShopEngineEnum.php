<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Dec 2025 16:36:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Shop;

use App\Enums\EnumHelperTrait;

enum ShopEngineEnum: string
{
    use EnumHelperTrait;

    case AIKU = 'aiku';
    case SHOPIFY = 'shopify';
    case FAIRE = 'faire';
    case WIX = 'wix';

    public function label(): String
    {
         return match ($this) {
            ShopEngineEnum::AIKU    =>  'AIKU',
            ShopEngineEnum::SHOPIFY =>  'Shopify',
            ShopEngineEnum::FAIRE   =>  'Faire',
            ShopEngineEnum::WIX     =>  'WIX',
         };
    }
}
