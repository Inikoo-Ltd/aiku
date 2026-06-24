<?php

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewContextEnum: string
{
    use EnumHelperTrait;

    case ProductReviews = 'product_reviews';
    case ShopReviews = 'shop_reviews';
    case ProductCategoryReviews = 'product_category_reviews';

    public static function labels(): array
    {
        return [
            self::ProductReviews->value => 'Product Reviews',
            self::ShopReviews->value => 'Shop Reviews',
            self::ProductCategoryReviews->value => 'Product Category Reviews',
        ];
    }
}
