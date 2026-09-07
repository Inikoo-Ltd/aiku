<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketKindEnum: string
{
    use EnumHelperTrait;

    case ESCALATION = 'escalation';
    case BUG        = 'bug';
    case FEATURE    = 'feature';

    public static function labels(): array
    {
        return [
            'escalation' => __('Escalated customer ticket'),
            'bug'        => __('Bug'),
            'feature'    => __('Feature request'),
        ];
    }
}
