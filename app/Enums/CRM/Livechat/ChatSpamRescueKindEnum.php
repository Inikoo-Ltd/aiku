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
    case SUPPLIER_PITCH = 'supplier_pitch';
    case SERVICE_PITCH = 'service_pitch';
    case SCAM = 'scam';
    case AUTOMATED = 'automated';

    /**
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'customer_request' => 'A customer asking about their order, delivery, invoice, payment, account, a product or a problem with it, with concrete details',
            'prospect'         => 'A named shop, business or seller that wants to buy from us, stock our products, open a trade account or dropship our products, and says what they sell or where',
            'supplier_pitch'   => 'A factory, manufacturer, exporter or supplier offering to sell their products or materials to us',
            'service_pitch'    => 'Someone offering us a service, including freelancers who ask vague questions about our store or dropshipping to start a conversation: marketing, SEO, ads, web or Shopify experts, apps, logistics, freight, packaging, printing, data',
            'scam'             => 'Phishing, fake invoices or payment copies, fake copyright or policy violations, fake verification badges, investment or funding offers, vague bulk purchase or long-term contract requests with no company or product details, prizes, bug bounty requests',
            'automated'        => 'An auto-reply, out of office, newsletter or notification sent by a machine',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => __('Customer request'),
            self::PROSPECT         => __('New customer enquiry'),
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
