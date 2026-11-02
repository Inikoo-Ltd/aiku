<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureArtisanDay;

use App\Enums\EnumHelperTrait;

enum ManufactureArtisanDayStatusEnum: string
{
    use EnumHelperTrait;

    case OK          = 'ok';
    case OVER        = 'over';
    case UNDER       = 'under';
    case RUNNING_JOB = 'running_job';
    case IN_PROGRESS = 'in_progress';
    case NO_SHIFT    = 'no_shift';
    case NO_BREAK    = 'no_break';

    public static function labels($forElements = false): array
    {
        return [
            'ok'          => __('Matches shift'),
            'over'        => __('Over shift time'),
            'under'       => __('Under'),
            'running_job' => __('Job left running'),
            'in_progress' => __('Shift not over'),
            'no_shift'    => __('No shift set'),
            'no_break'    => __('No break clocked'),
        ];
    }
}
