<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureTaskSession;

use App\Enums\EnumHelperTrait;

enum ManufactureTaskSessionUnderTargetReasonEnum: string
{
    use EnumHelperTrait;

    case MACHINE_BREAKDOWN = 'machine_breakdown';
    case MATERIAL_SHORTAGE = 'material_shortage';
    case QUALITY_REWORK    = 'quality_rework';
    case OPERATOR_TRAINING = 'operator_training';
    case OTHER             = 'other';

    public static function labels($forElements = false): array
    {
        return [
            'machine_breakdown' => __('Machine breakdown'),
            'material_shortage' => __('Material shortage'),
            'quality_rework'    => __('Quality / rework'),
            'operator_training' => __('Operator training'),
            'other'             => __('Other'),
        ];
    }
}
