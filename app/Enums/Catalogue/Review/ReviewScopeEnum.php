<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 19:11:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewScopeEnum: string
{
    use EnumHelperTrait;

    case ORDER = 'order';
    case FAMILY = 'family';
    case PRODUCT = 'product';
    case SHOP = 'shop'; // Used for imported external reviews

    public static function labels(): array
    {
        return [
            self::ORDER->value   => __('Order'),
            self::FAMILY->value  => __('Family'),
            self::PRODUCT->value => __('Product'),
            self::SHOP->value    => __('Shop'),
        ];
    }
}
