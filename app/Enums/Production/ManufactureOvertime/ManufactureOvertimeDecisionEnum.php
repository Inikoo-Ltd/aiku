<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureOvertime;

use App\Enums\EnumHelperTrait;

enum ManufactureOvertimeDecisionEnum: string
{
    use EnumHelperTrait;

    case APPROVED     = 'approved';
    case NOT_APPROVED = 'not_approved';

    public static function labels($forElements = false): array
    {
        return [
            'approved'     => __('Overtime approved'),
            'not_approved' => __('Overtime not authorised'),
        ];
    }
}
