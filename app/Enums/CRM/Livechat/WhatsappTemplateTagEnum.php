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

    case ORDER_NUMBER = 'Order Number';
    case ORDER_TOTAL = 'Order Total';
    case ORDER_DATE = 'Order Date';
    case ORDER_STATE = 'Order State';
    case ORDER_ITEMS_COUNT = 'Order Items Count';

    case INVOICE_NUMBER = 'Invoice Number';
    case INVOICE_TOTAL = 'Invoice Total';
    case INVOICE_DATE = 'Invoice Date';

    case DELIVERY_ADDRESS = 'Delivery Address';
    case DELIVERY_TOWN = 'Delivery Town';
    case DELIVERY_POSTCODE = 'Delivery Postcode';
    case DELIVERY_COUNTRY = 'Delivery Country';

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

            self::ORDER_NUMBER      => 'GB585956',
            self::ORDER_TOTAL       => '£125.00',
            self::ORDER_DATE        => '12 Aug 2026',
            self::ORDER_STATE       => 'Submitted',
            self::ORDER_ITEMS_COUNT => '7',

            self::INVOICE_NUMBER => 'AWP26i2481',
            self::INVOICE_TOTAL  => '£63.56',
            self::INVOICE_DATE   => '12 Aug 2026',

            self::DELIVERY_ADDRESS  => '10 Cochrane Close, Tipton, DY4 7DJ, United Kingdom',
            self::DELIVERY_TOWN     => 'Tipton',
            self::DELIVERY_POSTCODE => 'DY4 7DJ',
            self::DELIVERY_COUNTRY  => 'United Kingdom',

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
