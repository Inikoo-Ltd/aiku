<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketProjectStatusEnum: string
{
    use EnumHelperTrait;

    case ACTIVE    = 'active';
    case ON_HOLD   = 'on_hold';
    case DONE      = 'done';
    case CANCELLED = 'cancelled';

    public static function labels(): array
    {
        return [
            'active'    => __('Active'),
            'on_hold'   => __('On hold'),
            'done'      => __('Done'),
            'cancelled' => __('Cancelled'),
        ];
    }

    public static function options(): array
    {
        return collect(self::labels())->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])->values()->all();
    }
}
