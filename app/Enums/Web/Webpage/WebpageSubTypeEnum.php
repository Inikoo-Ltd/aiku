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


    case BLOG = 'blog';
    case ARTICLE = 'article';


    public static function labels(): array
    {
        return [
            'storefront'  => __('storefront'),
            'appointment' => __('appointment'),
            'login'       => __('login'),
            'register'    => __('register'),
            'blog'        => __('blog'),
            'article'     => __('article'),
            'content'     => __('content'),

        ];
    }
}
