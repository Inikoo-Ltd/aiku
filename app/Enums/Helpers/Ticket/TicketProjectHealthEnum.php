<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketProjectHealthEnum: string
{
    use EnumHelperTrait;

    case ON_TRACK  = 'on_track';
    case AT_RISK   = 'at_risk';
    case OFF_TRACK = 'off_track';

    public static function labels(): array
    {
        return [
            'on_track'  => __('On track'),
            'at_risk'   => __('At risk'),
            'off_track' => __('Off track'),
        ];
    }

    public static function options(): array
    {
        return collect(self::labels())->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])->values()->all();
    }
}
