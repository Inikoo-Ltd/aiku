<?php

/*
 * Author Louis Perez
 * Created on 11-08-2026-09h-32m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;

enum WebpageBlogTypeEnum: string
{
    use EnumHelperTrait;

    case BLOG           = 'blog';
    case NEWSLETTERS    = 'newsletters';
    case PRODUCT_GUIDES = 'product_guides';
    case BUSINESS_TIPS  = 'business_tips';
    case INSIGHT        = 'insight';

    public function label(): string
    {
        return match ($this) {
            self::NEWSLETTERS       => "Newsletters",
            self::PRODUCT_GUIDES    => "Product Guides",
            self::BUSINESS_TIPS     => "Business Tips",
            self::INSIGHT           => "Industry & Retail's Insight",
            default                 => "Blog",
        };
    }
}
