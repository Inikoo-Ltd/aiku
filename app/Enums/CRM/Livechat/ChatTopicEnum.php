<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What the customer wanted, one per conversation, chosen by the summariser from this list and
 * nothing else. A fixed list so it can be counted: a free description is different every time
 * and adds up to nothing. When a conversation covers two things, it is the one that made the
 * customer write.
 */
enum ChatTopicEnum: string
{
    use EnumHelperTrait;

    case ORDER_STATUS = 'order_status';
    case ORDER_CHANGE = 'order_change';
    case MISSING_OR_DAMAGED = 'missing_or_damaged';
    case RETURN_REFUND = 'return_refund';
    case PRODUCT_QUERY = 'product_query';
    case STOCK_AVAILABILITY = 'stock_availability';
    case PRICING_DISCOUNTS = 'pricing_discounts';
    case SHIPPING_QUERY = 'shipping_query';
    case PAYMENT_INVOICE = 'payment_invoice';
    case ACCOUNT_ACCESS = 'account_access';
    case WEBSITE_PROBLEM = 'website_problem';
    case DROPSHIPPING_INTEGRATION = 'dropshipping_integration';
    case COMPLAINT = 'complaint';
    case NO_REQUEST = 'no_request';
    case OTHER = 'other';

    public static function labels(): array
    {
        return [
            'order_status'             => __('Where is my order'),
            'order_change'             => __('Change or cancel an order'),
            'missing_or_damaged'       => __('Missing, damaged or wrong items'),
            'return_refund'            => __('Return or refund'),
            'product_query'            => __('Product question'),
            'stock_availability'       => __('Stock availability'),
            'pricing_discounts'        => __('Prices and discounts'),
            'shipping_query'           => __('Shipping question'),
            'payment_invoice'          => __('Payment or invoice'),
            'account_access'           => __('Account and login'),
            'website_problem'          => __('Website problem'),
            'dropshipping_integration' => __('Dropshipping and integrations'),
            'complaint'                => __('Complaint'),
            'no_request'               => __('No request'),
            'other'                    => __('Other'),
        ];
    }

    /**
     * What the summariser is told each one means. English, because it is read by the model and
     * never by a person.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'order_status'             => 'asking where an order is, tracking, when it will ship or arrive',
            'order_change'             => 'wants to change, add to, merge or cancel an order already placed, or fix its address',
            'missing_or_damaged'       => 'reports items missing from a delivery, damaged, faulty or the wrong item sent',
            'return_refund'            => 'wants to return goods or asks for a refund or credit, for any other reason',
            'product_query'            => 'question about a product: ingredients, size, safety, how it is used, certificates',
            'stock_availability'       => 'asks if or when a product is or will be back in stock',
            'pricing_discounts'        => 'prices, discounts, vouchers, minimum order, wholesale terms',
            'shipping_query'           => 'delivery costs, methods, countries or times before ordering, customs and duties',
            'payment_invoice'          => 'a payment that failed or is unclear, asking for an invoice, VAT',
            'account_access'           => 'registration, approval, login, password, changing account details',
            'website_problem'          => 'the website or checkout not working, an error, something that cannot be found',
            'dropshipping_integration' => 'dropshipping, Shopify, eBay, Amazon or other channel connections, the API, product feeds',
            'complaint'                => 'unhappy with the service itself and none of the above fits better',
            'no_request'               => 'only a greeting, a test, a thank you, or nothing that asks for anything',
            'other'                    => 'a real request that fits none of the above',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
