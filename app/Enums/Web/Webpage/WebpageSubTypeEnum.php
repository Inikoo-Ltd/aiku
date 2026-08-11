<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 01:32:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;

enum WebpageSubTypeEnum: string
{
    use EnumHelperTrait;

    case STOREFRONT = 'storefront';
    case CATALOGUE = 'catalogue';
    case PRODUCTS = 'products';
    case PRODUCT = 'product';
    case FAMILY = 'family';
    case DEPARTMENT = 'department';
    case SUB_DEPARTMENT = 'sub_department';
    case COLLECTION = 'collection';
    case LANDING_PAGE = 'landing_page';
    case CONTENT = 'content';
    case ABOUT_US = 'about-us';
    case CONTACT = 'contact';
    case RETURNS = 'returns';
    case SHIPPING = 'shipping';
    case SHOWROOM = 'showroom';
    case TERMS_AND_CONDITIONS = 'terms-and-conditions';
    case PRIVACY = 'privacy';
    case COOKIES_POLICY = 'cookies-policy';
    case BASKET = 'basket';
    case CHECKOUT = 'checkout';
    case LOGIN = 'login';
    case REGISTER = 'register';
    case CALL_BACK = 'call_back';
    case APPOINTMENT = 'appointment';
    case PRICING = 'pricing';
    case ARTICLE = 'article';
    case MAILSHOT = 'mailshot';

    case BLOG = 'blog';
    case DAVID_AW_NEWS  = 'david_aw_news';
    case PRODUCT_GUIDES = 'product_guides';
    case BUSINESS_TIPS  = 'business_tips';
    case INSIGHT        = 'insight';

    public static function labels(): array
    {
        return [
            'storefront'            => __('Storefront'),
            'appointment'           => __('Appointment'),
            'login'                 => __('Login'),
            'register'              => __('Register'),
            'mailshot'              => __('Mailshot'),
            'article'               => __('Article'),
            'content'               => __('Content'),
            
            'blog'                  => __('Blog'),
            'insight'               => __('Industry & Retail Insights'),
            'david_aw_news'         => __("David's Travel Blog"),
            'product_guides'        => __('Product Guides'),
            'business_tips'         => __('Business Tips'),
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function blogCategories(): array
    {
        return [
            self::BLOG,
            self::DAVID_AW_NEWS,
            self::PRODUCT_GUIDES,
            self::BUSINESS_TIPS,
            self::INSIGHT,
        ];
    }

    public static function blogCategoriesWithLabel(): array
    {
        return [
            [
                'value' => self::BLOG->value,
                'label' => __("Blog"),
            ],
            [
                'value' => self::DAVID_AW_NEWS->value,
                'label' => __("David's Travel Blog"),
            ],
            [
                'value' => self::PRODUCT_GUIDES->value,
                'label' => __("Product Guides"),
            ],
            [
                'value' => self::BUSINESS_TIPS->value,
                'label' => __("Business Tips"),
            ],
            [
                'value' => self::INSIGHT->value,
                'label' => __("Industry & Retail's Insight"),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function blogCategoryValues(): array
    {
        return array_map(fn (self $subType) => $subType->value, self::blogCategories());
    }

    public static function catalogueLabels(): array
    {
        return [
            self::PRODUCT->value           => 'Product',
            self::FAMILY->value            => 'Family',
            self::DEPARTMENT->value        => 'Department',
            self::SUB_DEPARTMENT->value    => 'Sub Department',
            self::COLLECTION->value        => 'Collection',
        ];
    }

    public static function catalogueCount(): array
    {
        return [
            self::PRODUCT->value           => null,
            self::FAMILY->value            => null,
            self::DEPARTMENT->value        => null,
            self::SUB_DEPARTMENT->value    => null,
            self::COLLECTION->value        => null,
        ];
    }
}
