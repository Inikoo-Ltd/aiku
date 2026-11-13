<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * Merge tags for WhatsApp templates, in the same bracket notation the email outbox uses
 * (see OutboxMergeTagsEnum) so both channels feel the same to whoever writes the message.
 *
 * WhatsApp itself only understands positional `{{1}}`, `{{2}}` placeholders, so a tag is
 * a purely editorial concept: it is swapped for its position when the template is
 * submitted, and the order is remembered so real values can be filled in at send time.
 *
 * The samples below mirror the shape of real Aiku data (order references look like
 * `GB585956`, invoices like `AWP26i2481`) because Meta's reviewer judges the template by
 * how it reads once filled in. They are never sent to a customer.
 */
enum WhatsappTemplateTagEnum: string
{
    use EnumHelperTrait;

    case CUSTOMER_NAME = 'Customer Name';
    case CUSTOMER_FIRST_NAME = 'Customer First Name';
    case CUSTOMER_COMPANY = 'Customer Company';
    case CUSTOMER_EMAIL = 'Customer Email';
    case CUSTOMER_PHONE = 'Customer Phone';
    case CUSTOMER_REFERENCE = 'Customer Reference';
    case CUSTOMER_REGISTER_DATE = 'Customer Register Date';
    case CUSTOMER_BALANCE = 'Customer Balance';

    case SHOP_NAME = 'Shop Name';
    case SHOP_URL = 'Shop Url';
    case SHOP_EMAIL = 'Shop Email';
    case SHOP_PHONE = 'Shop Phone';
    case SHOP_BANK_DETAILS = 'Shop Bank Details';

    case ORDER_NUMBER = 'Order Number';
    case ORDER_TOTAL = 'Order Total';
    case ORDER_DATE = 'Order Date';
    case ORDER_STATE = 'Order State';
    case ORDER_ITEMS_COUNT = 'Order Items Count';
    case ORDER_TIME = 'Order Time';
    case ORDER_URL = 'Order Url';
    case ORDER_AMOUNT_DUE = 'Order Amount Due';
    case ORDER_PAYMENT_DEADLINE = 'Order Payment Deadline';
    case ORDER_DISPATCH_DATE = 'Order Dispatch Date';

    case PRE_ORDER_UPDATE = 'Pre-order Update';
    case PRE_ORDER_BALANCE_DUE_DATE = 'Pre-order Balance Due Date';
    case PRE_ORDER_DISPATCH_DATES = 'Pre-order Dispatch Dates';

    case CREDIT_AMOUNT = 'Credit Amount';
    case CREDIT_REASON = 'Credit Reason';

    case CHAT_URL = 'Chat Url';

    case INVOICE_NUMBER = 'Invoice Number';
    case INVOICE_TOTAL = 'Invoice Total';
    case INVOICE_DATE = 'Invoice Date';

    case DELIVERY_ADDRESS = 'Delivery Address';
    case DELIVERY_TOWN = 'Delivery Town';
    case DELIVERY_POSTCODE = 'Delivery Postcode';
    case DELIVERY_COUNTRY = 'Delivery Country';

    case PRODUCT_NAMES = 'Product Names';
    case PRODUCT_COUNT = 'Product Count';
    case PRODUCT_URL = 'Product Url';

    case TRACKING_CARRIER = 'Tracking Carrier';
    case TRACKING_NUMBER = 'Tracking Number';
    case TRACKING_URL = 'Tracking Url';

    case AGENT_NAME = 'Agent Name';
    case AGENT_FIRST_NAME = 'Agent First Name';

    /**
     * @return array<int, array{name: string, value: string, example: string, group: string}>
     */
    public static function tags(): array
    {
        return array_map(
            fn (self $tag) => [
                'name'    => __($tag->value),
                'value'   => '['.$tag->value.']',
                'example' => $tag->example(),
                'group'   => $tag->group(),
            ],
            self::cases()
        );
    }

    public function example(): string
    {
        return match ($this) {
            self::CUSTOMER_NAME          => 'Andi Ferdiawan',
            self::CUSTOMER_FIRST_NAME    => 'Andi',
            self::CUSTOMER_COMPANY       => 'Ancient Wisdom Ltd',
            self::CUSTOMER_EMAIL         => 'andi@example.com',
            self::CUSTOMER_PHONE         => '+44 7599 663334',
            self::CUSTOMER_REFERENCE     => 'AWD08605',
            self::CUSTOMER_REGISTER_DATE => '12 Aug 2026',
            self::CUSTOMER_BALANCE       => '£125.00',

            self::SHOP_NAME  => 'Ancient Wisdom',
            self::SHOP_URL   => 'https://www.ancientwisdom.biz',
            self::SHOP_EMAIL => 'sales@ancientwisdom.biz',
            self::SHOP_PHONE => '+44 1246 264590',
            self::SHOP_BANK_DETAILS => 'Ancient Wisdom Ltd, Sort code 00-00-00, Account 00000000',

            self::ORDER_NUMBER      => 'GB585956',
            self::ORDER_TOTAL       => '£125.00',
            self::ORDER_DATE        => '12 Aug 2026',
            self::ORDER_STATE       => 'Submitted',
            self::ORDER_ITEMS_COUNT => '7',
            self::ORDER_TIME        => '14:35',
            self::ORDER_URL         => 'https://www.ancientwisdom.biz/app/orders/gb585956',
            self::ORDER_AMOUNT_DUE  => '£125.00',
            self::ORDER_PAYMENT_DEADLINE => '19 Aug 2026',
            self::ORDER_DISPATCH_DATE    => '13 Aug 2026',

            self::PRE_ORDER_UPDATE           => 'the balance of £80.00 is due by 20 Aug 2026',
            self::PRE_ORDER_BALANCE_DUE_DATE => '20 Aug 2026',
            self::PRE_ORDER_DISPATCH_DATES   => '1 Sep 2026 to 15 Sep 2026',

            self::CREDIT_AMOUNT => '£25.00',
            self::CREDIT_REASON => 'Refund',

            self::CHAT_URL => 'https://www.ancientwisdom.biz/?chat_session=01JC0000000000000000000000',

            self::INVOICE_NUMBER => 'AWP26i2481',
            self::INVOICE_TOTAL  => '£63.56',
            self::INVOICE_DATE   => '12 Aug 2026',

            self::DELIVERY_ADDRESS  => '10 Cochrane Close, Tipton, DY4 7DJ, United Kingdom',
            self::DELIVERY_TOWN     => 'Tipton',
            self::DELIVERY_POSTCODE => 'DY4 7DJ',
            self::DELIVERY_COUNTRY  => 'United Kingdom',

            self::PRODUCT_NAMES => 'Lavender Essential Oil 10ml, Rose Bath Bomb',
            self::PRODUCT_COUNT => '2',
            self::PRODUCT_URL   => 'https://www.ancientwisdom.biz/lavender-essential-oil-10ml',

            self::TRACKING_CARRIER => 'DPD',
            self::TRACKING_NUMBER  => '15501234567890',
            self::TRACKING_URL     => 'https://track.dpdlocal.co.uk/parcels/15501234567890',

            self::AGENT_NAME       => 'Rhianne Cartledge',
            self::AGENT_FIRST_NAME => 'Rhianne',
        };
    }

    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'Customer') => __('Customer'),
            str_starts_with($this->value, 'Shop')     => __('Shop'),
            str_starts_with($this->value, 'Order')    => __('Order'),
            str_starts_with($this->value, 'Invoice')  => __('Invoice'),
            str_starts_with($this->value, 'Delivery') => __('Delivery'),
            str_starts_with($this->value, 'Tracking') => __('Tracking'),
            str_starts_with($this->value, 'Product')  => __('Product'),
            str_starts_with($this->value, 'Pre-order') => __('Pre-order'),
            str_starts_with($this->value, 'Credit')   => __('Credit'),
            str_starts_with($this->value, 'Chat')     => __('Chat'),
            default                                   => __('Agent'),
        };
    }

    public static function fromToken(string $token): ?self
    {
        return self::tryFrom(trim($token, '[]'));
    }

    /**
     * Matches every `[Tag]` occurrence, including ones that are not real tags, so the
     * caller can tell a typo apart from a tag it simply does not know.
     */
    public static function tokenPattern(): string
    {
        return '/\[([^\[\]]+)\]/';
    }
}
