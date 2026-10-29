<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * The one thing a customer message asks for, finer than its topic. Drafts are counted per shop
 * and intent, so the answers that staff send as written can be told apart from the ones they
 * rewrite, and only an intent that earned it is ever answered without a person. A fixed list,
 * each inside one topic.
 */
enum ChatIntentEnum: string
{
    use EnumHelperTrait;

    case CONNECT_STORE = 'connect_store';
    case SYNC_PROBLEM = 'sync_problem';
    case PRODUCT_DATA_EXPORT = 'product_data_export';
    case DROPSHIP_TERMS = 'dropship_terms';
    case PRODUCT_DOCUMENTS = 'product_documents';
    case PRODUCT_DETAILS = 'product_details';
    case PRODUCT_SOURCING = 'product_sourcing';
    case BACK_IN_STOCK = 'back_in_stock';
    case PRICE_QUOTE = 'price_quote';
    case DISCOUNT_VOUCHER = 'discount_voucher';
    case WHERE_IS_ORDER = 'where_is_order';
    case DELIVERY_FAILED = 'delivery_failed';
    case CANCEL_ORDER = 'cancel_order';
    case CHANGE_ORDER_ITEMS = 'change_order_items';
    case CHANGE_ADDRESS = 'change_address';
    case DAMAGED_ITEM = 'damaged_item';
    case MISSING_ITEM = 'missing_item';
    case WRONG_ITEM = 'wrong_item';
    case RETURN_REQUEST = 'return_request';
    case REFUND_STATUS = 'refund_status';
    case INVOICE_COPY = 'invoice_copy';
    case PAYMENT_PROBLEM = 'payment_problem';
    case ACCOUNT_BALANCE = 'account_balance';
    case SHIPPING_COSTS_TIMES = 'shipping_costs_times';
    case CUSTOMS_DUTIES = 'customs_duties';
    case REGISTRATION_APPROVAL = 'registration_approval';
    case LOGIN_PASSWORD = 'login_password';
    case ACCOUNT_DETAILS = 'account_details';
    case WEBSITE_PROBLEM = 'website_problem';
    case COMPLAINT_SERVICE = 'complaint_service';
    case THANKS_ONLY = 'thanks_only';
    case INFORMATION_GIVEN = 'information_given';
    case BUSINESS_PROPOSAL = 'business_proposal';
    case OTHER = 'other';

    public function topic(): ChatTopicEnum
    {
        return match ($this) {
            self::CONNECT_STORE, self::SYNC_PROBLEM, self::PRODUCT_DATA_EXPORT, self::DROPSHIP_TERMS => ChatTopicEnum::DROPSHIPPING_INTEGRATION,
            self::PRODUCT_DOCUMENTS, self::PRODUCT_DETAILS, self::PRODUCT_SOURCING                    => ChatTopicEnum::PRODUCT_QUERY,
            self::BACK_IN_STOCK                                                                      => ChatTopicEnum::STOCK_AVAILABILITY,
            self::PRICE_QUOTE, self::DISCOUNT_VOUCHER                                                => ChatTopicEnum::PRICING_DISCOUNTS,
            self::WHERE_IS_ORDER, self::DELIVERY_FAILED                                              => ChatTopicEnum::ORDER_STATUS,
            self::CANCEL_ORDER, self::CHANGE_ORDER_ITEMS, self::CHANGE_ADDRESS                       => ChatTopicEnum::ORDER_CHANGE,
            self::DAMAGED_ITEM, self::MISSING_ITEM, self::WRONG_ITEM                                 => ChatTopicEnum::MISSING_OR_DAMAGED,
            self::RETURN_REQUEST, self::REFUND_STATUS                                                => ChatTopicEnum::RETURN_REFUND,
            self::INVOICE_COPY, self::PAYMENT_PROBLEM, self::ACCOUNT_BALANCE                         => ChatTopicEnum::PAYMENT_INVOICE,
            self::SHIPPING_COSTS_TIMES, self::CUSTOMS_DUTIES                                         => ChatTopicEnum::SHIPPING_QUERY,
            self::REGISTRATION_APPROVAL, self::LOGIN_PASSWORD, self::ACCOUNT_DETAILS                 => ChatTopicEnum::ACCOUNT_ACCESS,
            self::WEBSITE_PROBLEM                                                                    => ChatTopicEnum::WEBSITE_PROBLEM,
            self::COMPLAINT_SERVICE                                                                  => ChatTopicEnum::COMPLAINT,
            self::THANKS_ONLY, self::INFORMATION_GIVEN                                               => ChatTopicEnum::NO_REQUEST,
            self::BUSINESS_PROPOSAL, self::OTHER                                                     => ChatTopicEnum::OTHER,
        };
    }

    /**
     * What each one means, for the routine and Jev. English, because it is read by a model.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'connect_store'         => 'how to connect their online store or marketplace to us (Shopify, WooCommerce, Wix, eBay, TikTok, Amazon, the API)',
            'sync_problem'          => 'products, stock or prices not syncing to their store, or their store orders not reaching us',
            'product_data_export'   => 'product data, CSV feeds, photos or image downloads for their own store',
            'dropship_terms'        => 'how dropshipping with us works: fees, terms, branding, blind shipping, how orders are paid',
            'product_documents'     => 'a safety or compliance document for a product: CPSR, SDS, IFRA, CLP, allergens, certificates, labels',
            'product_details'       => 'what a product is: ingredients, size, weight, materials, scent, how to use it',
            'product_sourcing'      => 'whether we sell or can make something: a product we do not list, new lines, own label, custom work',
            'back_in_stock'         => 'whether or when a product is or will be back in stock',
            'price_quote'           => 'prices for a product or quantity, wholesale terms, minimum order',
            'discount_voucher'      => 'a discount, voucher or offer: how to get it, or why it was not applied',
            'where_is_order'        => 'where an order is, its tracking, when it will ship or arrive',
            'delivery_failed'       => 'a carrier could not deliver: returned to sender, failed attempt, address problem at delivery',
            'cancel_order'          => 'cancel an order they placed, or not send it',
            'change_order_items'    => 'add, remove or swap items on an order they placed',
            'change_address'        => 'change or correct the delivery address of an order they placed',
            'damaged_item'          => 'items arrived damaged, broken or faulty',
            'missing_item'          => 'items missing from a delivery',
            'wrong_item'            => 'the wrong item was sent',
            'return_request'        => 'return goods for a reason other than damage, missing or wrong items',
            'refund_status'         => 'where a refund or credit we promised is',
            'invoice_copy'          => 'send an invoice, a copy of one, or a VAT invoice',
            'payment_problem'       => 'a payment that failed, was taken twice, is unclear, or how to pay',
            'account_balance'       => 'the balance or credit on their account',
            'shipping_costs_times'  => 'delivery costs, methods, countries or times, before ordering',
            'customs_duties'        => 'customs, duties, import taxes or paperwork for a delivery',
            'registration_approval' => 'registering, getting a trade account approved, what is needed to open one',
            'login_password'        => 'cannot log in, password reset',
            'account_details'       => 'change their email, address, VAT number or other account details',
            'website_problem'       => 'the website or checkout not working, an error, something that cannot be found',
            'complaint_service'     => 'unhappy with the service itself, and nothing above fits better',
            'thanks_only'           => 'only thanks, a goodbye or confirming they received something',
            'information_given'     => 'they only tell us something: they paid, sent photos, will send something, an update',
            'business_proposal'     => 'a supplier, partnership, marketing or sales pitch to us',
            'other'                 => 'a real request that fits none of the above',
        ];
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
