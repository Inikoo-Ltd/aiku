<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureOvertime;

use App\Enums\EnumHelperTrait;

enum ManufactureOvertimeCompensationEnum: string
{
    use EnumHelperTrait;

    case PAID = 'paid';
    case TOIL = 'toil';

    public static function labels($forElements = false): array
    {
        return [
            'paid' => __('Paid'),
            'toil' => __('Time off in lieu'),
        ];
    }
}
