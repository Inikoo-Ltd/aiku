<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\CustomerClaim;

use App\Enums\EnumHelperTrait;

enum CustomerClaimStateEnum: string
{
    use EnumHelperTrait;

    case IN_REVIEW = 'in_review';
    case SETTLED = 'settled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::IN_REVIEW => __('Being reviewed'),
            self::SETTLED   => __('Settled'),
            self::REJECTED  => __('Rejected'),
        };
    }
}
