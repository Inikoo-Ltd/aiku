<?php

/*
 * author Arya Permana - Kirin
 * created on 28-10-2024-10h-42m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\Accounting\InvoiceCategory;

use App\Enums\EnumHelperTrait;

enum InvoiceCategoryTypeEnum: string
{
    use EnumHelperTrait;

    case SHOP_TYPE = 'shop_type';
    case SHOP_FALLBACK = 'shop_fallback';
    case IN_COUNTRY = 'in_country';
    case NOT_IN_COUNTRY = 'not_in_country';
    case IN_ORGANISATION = 'in_organisation';
    case VIP = 'vip';
    case EXTERNAL_INVOICER = 'external_invoicer';
    case IN_SALES_CHANNEL = 'in_sales_channel';
    case IN_SALES_CHANNEL_SHOP = 'in_sales_channel_shop';

    public static function labels(): array
    {
        return [
            'shop_type' => __('Shop type'),
            'shop_fallback' => __('Shop fallback'),
            'in_country' => __('In country'),
            'not_in_country' => __('Not in country'),
            'in_organisation' => __('In organisation'),
            'vip' => __('VIP'),
            'external_invoicer' => __('External invoicer'),
            'in_sales_channel' => __('In sales channel'),
            'in_sales_channel_shop' => __('In sales channel/shop'),
        ];
    }
}
