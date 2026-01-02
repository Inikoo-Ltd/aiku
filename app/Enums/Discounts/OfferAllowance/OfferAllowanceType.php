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
    case UNKNOWN = 'unknown'; // user for aurora migrations

    public function label(): string
    {
        return match ($this) {
            OfferAllowanceType::PERCENTAGE_OFF => __('Percentage Off'),
            OfferAllowanceType::UNKNOWN => __('Unknown'),
        };
    }

    public function slug(): string
    {
        return match ($this) {
            OfferAllowanceType::PERCENTAGE_OFF => 'off',
            OfferAllowanceType::UNKNOWN => 'au',
        };
    }


}
