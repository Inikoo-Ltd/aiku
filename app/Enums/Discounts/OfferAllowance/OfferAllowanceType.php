<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Oct 2025 17:13:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\OfferAllowance;

enum OfferAllowanceType: string
{
    case PERCENTAGE_OFF = 'percentage_off';
    case AMOUNT_OFF = 'amount_off';
    case FREE_ITEMS = 'free_items';
    case GIFT = 'gift';
    case MIXED = 'mixed';
    case SHIPPING = 'shipping';
    case UNKNOWN = 'unknown'; // user for aurora migrations

    public function label(): string
    {
        return match ($this) {
            OfferAllowanceType::PERCENTAGE_OFF => __('Percentage Off'),
            OfferAllowanceType::AMOUNT_OFF => __('Amount Off'),
            OfferAllowanceType::FREE_ITEMS => __('Free Items'),
            OfferAllowanceType::GIFT => __('Gift'),
            OfferAllowanceType::UNKNOWN => __('Unknown'),
            OfferAllowanceType::MIXED => __('Mixed'),
            OfferAllowanceType::SHIPPING => __('Shipping'),
        };
    }

    public function slug(): string
    {
        return match ($this) {
            OfferAllowanceType::PERCENTAGE_OFF => 'off',
            OfferAllowanceType::AMOUNT_OFF => 'amount_off',
            OfferAllowanceType::FREE_ITEMS => 'free',
            OfferAllowanceType::UNKNOWN => 'au',
            OfferAllowanceType::GIFT => 'gift',
            OfferAllowanceType::MIXED => 'mixed',
            OfferAllowanceType::SHIPPING => 'shipping',
        };
    }


}
