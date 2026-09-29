<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What Jev took a stranger's email in Gmail spam to be. The definitions are the options Jev
 * chooses from, so a change here changes what comes in. Kept on the message so the agent knows
 * what Aiku thought it was letting in.
 */
enum ChatSpamRescueKindEnum: string
{
    use EnumHelperTrait;

    case CUSTOMER_REQUEST = 'customer_request';
    case PROSPECT = 'prospect';
    case VAGUE_BUYER = 'vague_buyer';
    case NOT_OUR_PRODUCTS = 'not_our_products';
    case SUPPLIER_PITCH = 'supplier_pitch';
    case SERVICE_PITCH = 'service_pitch';
    case SCAM = 'scam';
    case AUTOMATED = 'automated';

    /**
     * Tested on 292 real emails from Gmail spam: every one of the 30 genuine customers and
     * prospects kept, junk let in down from 15 to 1 against the first, six-kind wording.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'customer_request' => 'Somebody who already buys from us or has an account with us, asking about their order, delivery, invoice, payment, account, a product they bought, or setting up their dropshipping store with us, with the details written in the email itself',
            'prospect'         => 'A shop, business or seller that wants to buy from us, stock our products or dropship our catalogue, and says what it sells and which kinds of our products it wants: gifts, incense, candles, soaps, bath products, crystals, jewellery, lamps, home fragrance, homeware, pots, pet accessories and similar',
            'vague_buyer'      => 'A buying or partnership request that could be sent unchanged to any company: asks for a catalogue, product list, price list, supply capacity or quotation without naming any product, from a sourcing company, procurement manager, consultant or would-be sales representative, or a follow-up to an earlier message of that kind',
            'not_our_products' => 'Somebody who wants to buy goods we do not sell, such as machinery, raw materials, metals, gemstones in bulk, building materials or industrial supplies',
            'supplier_pitch'   => 'A factory, manufacturer, exporter or supplier offering to sell their products or materials to us, including white label and private label manufacturing offers',
            'service_pitch'    => 'Someone offering us a service, including freelancers who ask vague questions about our store or dropshipping to start a conversation or offer to bring orders for a commission: marketing, SEO, ads, web or Shopify experts, apps, logistics, freight, packaging, printing, data',
            'scam'             => 'Phishing or fraud: the order, invoice, damage report, purchase requirements or payment are only behind a link or attachment instead of written in the email, fake payment copies, fake copyright or policy violations, fake verification badges, investment or funding offers, crypto payment, prizes, bug bounty requests',
            'automated'        => 'An auto-reply, out of office, newsletter or notification sent by a machine',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => __('Customer request'),
            self::PROSPECT         => __('New customer enquiry'),
            self::VAGUE_BUYER      => __('Vague buying request'),
            self::NOT_OUR_PRODUCTS => __('Wants products we do not sell'),
            self::SUPPLIER_PITCH   => __('Supplier offer'),
            self::SERVICE_PITCH    => __('Service offer'),
            self::SCAM             => __('Possible scam'),
            self::AUTOMATED        => __('Automated email'),
        };
    }

    public function isWanted(): bool
    {
        return in_array($this, [self::CUSTOMER_REQUEST, self::PROSPECT], true);
    }
}
