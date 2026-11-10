<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;

enum CustomerShopTypeEnum: string
{
    use EnumHelperTrait;

    case CRYSTAL_NEW_AGE = 'crystal_new_age';
    case GIFT_SHOP = 'gift_shop';
    case SPA_WELLNESS = 'spa_wellness';
    case GARDEN_CENTRE = 'garden_centre';
    case ONLINE_SELLER = 'online_seller';
    case MARKET_TRADER = 'market_trader';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'crystal_new_age' => __('Crystal / new age shop'),
            'gift_shop'       => __('Gift shop'),
            'spa_wellness'    => __('Spa / wellness'),
            'garden_centre'   => __('Garden centre'),
            'online_seller'   => __('Online seller / dropshipper'),
            'market_trader'   => __('Market trader'),
            'other'           => __('Other'),
        ];
    }
}
