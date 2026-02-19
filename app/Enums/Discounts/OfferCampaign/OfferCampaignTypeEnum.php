<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 14:26:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\OfferCampaign;

use App\Enums\EnumHelperTrait;

enum OfferCampaignTypeEnum: string
{
    use EnumHelperTrait;

    case ORDER_RECURSION = 'order-recursion';
    case VOLUME_DISCOUNT = 'volume-discount';
    case FIRST_ORDER = 'first-order';
    case CUSTOMER_OFFERS = 'customer-offers';
    case SHOP_OFFERS = 'shop-offers';
    case CATEGORY_OFFERS = 'category-offers';
    case PRODUCT_OFFERS = 'product-offers';
    case DISCRETIONARY = 'discretionary';


    public function labels(): array
    {
        return [
            'order-recursion' => __('Order recursion'),
            'volume-discount' => __('Volume discount'),
            'first-order'     => __('First order'),
            'customer-offers' => __('Customer offers'),
            'shop-offers'     => __('Shop offers'),
            'category-offers' => __('Category offers'),
            'product-offers'  => __('Product offers'),
            'discretionary'   => __('Discretionary discounts')
        ];
    }

    public function codes(): array
    {
        return [
            'order-recursion' => 'OR',
            'volume-discount' => 'VL',
            'first-order'     => 'FO',
            'customer-offers' => 'CU',
            'shop-offers'     => 'SO',
            'category-offers' => 'CO',
            'product-offers'  => 'PO',
            'discretionary'   => 'DI'
        ];
    }

    public function icons(): array
    {
        return [
            'order-recursion' => [
                'icon'          => 'fal fa-repeat',
                'tooltip'       => self::from('order-recursion')->labels()['order-recursion'] ?? 'Unknown',
                'class'         => '',
            ],
            'volume-discount' => [
                'icon'          => 'fal fa-percentage',
                'tooltip'       => self::from('volume-discount')->labels()['volume-discount'] ?? 'Unknown',
                'class'         => '',
            ],
            'first-order' => [
                'icon'          => 'fal fa-flag',
                'tooltip'       => self::from('first-order')->labels()['first-order'] ?? 'Unknown',
                'class'         => '',
            ],
            'customer-offers' => [
                'icon'          => 'fal fa-users',
                'tooltip'       => self::from('customer-offers')->labels()['customer-offers'] ?? 'Unknown',
                'class'         => '',
            ],
            'shop-offers' => [
                'icon'          => 'fal fa-store',
                'tooltip'       => self::from('shop-offers')->labels()['shop-offers'] ?? 'Unknown',
                'class'         => '',
            ],
            'category-offers' => [
                'icon'          => 'fal fa-tags',
                'tooltip'       => self::from('category-offers')->labels()['category-offers'] ?? 'Unknown',
                'class'         => '',
            ],
            'product-offers' => [
                'icon'          => 'fal fa-box',
                'tooltip'       => self::from('product-offers')->labels()['product-offers'] ?? 'Unknown',
                'class'         => '',
            ],
            'discretionary' => [
                'icon'          => 'fal fa-hand-holding-usd',
                'tooltip'       => self::from('discretionary')->labels()['discretionary'] ?? 'Unknown',
                'class'         => '',
            ],
        ];
    }
}
