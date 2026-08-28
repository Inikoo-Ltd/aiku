<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 21:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureTaskSession;

use App\Enums\EnumHelperTrait;

enum ManufactureTaskSessionStateEnum: string
{
    use EnumHelperTrait;

    case OPEN   = 'open';
    case CLOSED = 'closed';
    case VOIDED = 'voided';

    public static function labels($forElements = false): array
    {
        return [
            'open'   => __('Open'),
            'closed' => __('Closed'),
            'voided' => __('Voided'),
        ];
    }
}
