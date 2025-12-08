<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 16:42:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\Offer;

use App\Enums\EnumHelperTrait;

/**
 * Duration of an offer or offer allowance.
 */
enum OfferDurationEnum: string
{
    use EnumHelperTrait;

    case PERMANENT = 'permanent';
    case INTERVAL = 'interval';

    public static function labels(): array
    {
        return [
            self::PERMANENT->value => __('Permanent'),
            self::INTERVAL->value => __('Interval'),
        ];
    }
}
