<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\AspoDeposit;

use App\Enums\EnumHelperTrait;

enum DepositRequestStateEnum: string
{
    use EnumHelperTrait;

    case REQUESTED = 'requested';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public static function labels(): array
    {
        return [
            'requested' => __('Requested'),
            'settled'   => __('Settled'),
            'cancelled' => __('Cancelled'),
        ];
    }
}
