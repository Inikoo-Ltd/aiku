<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\AspoDeposit;

use App\Enums\EnumHelperTrait;

enum AspoDepositStateEnum: string
{
    use EnumHelperTrait;

    case PENDING = 'pending';
    case PAID_TO_SUPPLIER = 'paid_to_supplier';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';

    public static function labels(): array
    {
        return [
            'pending'          => __('Pending'),
            'paid_to_supplier' => __('Paid to supplier'),
            'refunded'         => __('Refunded'),
            'cancelled'        => __('Cancelled'),
        ];
    }
}
