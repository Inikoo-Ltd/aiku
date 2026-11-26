<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureTaskSession;

use App\Enums\EnumHelperTrait;

enum ManufactureTaskSessionBandOverrideReasonEnum: string
{
    use EnumHelperTrait;

    case ARTISAN_ERROR_REWORK         = 'artisan_error_rework';
    case WRONG_MATERIALS_NOT_REPORTED = 'wrong_materials_not_reported';
    case QUALITY_REJECT               = 'quality_reject';
    case OTHER                        = 'other';

    public static function labels($forElements = false): array
    {
        return [
            'artisan_error_rework'         => __('Artisan error, to be redone'),
            'wrong_materials_not_reported' => __('Wrong materials used, not reported'),
            'quality_reject'               => __('Quality reject'),
            'other'                        => __('Other'),
        ];
    }
}
