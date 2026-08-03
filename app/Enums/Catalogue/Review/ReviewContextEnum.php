<?php

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewContextEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case ORDER = 'order';
    case FAMILY = 'family';
    case SHOP = 'shop';

    public static function labels(): array
    {
        return [
            self::PRODUCT->value => __('Product Reviews'),
            self::ORDER->value   => __('Overall Reviews'),
            self::FAMILY->value  => __('Family Reviews'),
            self::SHOP->value  => __('Shop Reviews'),
        ];
    }

    public static function shortLabels(): array
    {
        return [
            self::PRODUCT->value => __('Product'),
            self::ORDER->value   => __('Overall'),
            self::FAMILY->value  => __('Family'),
            self::SHOP->value  => __('Shop'),
        ];
    }
}
