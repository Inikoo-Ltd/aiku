<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ManufactureTaskSession;

use App\Enums\EnumHelperTrait;

enum ManufactureTaskSessionActivityTypeEnum: string
{
    use EnumHelperTrait;

    case PRODUCTION          = 'production';
    case SETUP               = 'setup';
    case CHANGEOVER          = 'changeover';
    case CLEANING            = 'cleaning';
    case WAITING_MATERIALS   = 'waiting_materials';
    case MACHINE_DOWN        = 'machine_down';
    case TRAINING            = 'training';
    case MEETING             = 'meeting';
    case MAINTENANCE         = 'maintenance';
    case DEVELOPMENT         = 'development';
    case OTHER               = 'other';

    public static function labels($forElements = false): array
    {
        return [
            'production'        => __('Production'),
            'setup'             => __('Setup'),
            'changeover'        => __('Changeover'),
            'cleaning'          => __('Cleaning'),
            'waiting_materials' => __('Waiting materials'),
            'machine_down'      => __('Machine down'),
            'training'          => __('Training'),
            'meeting'           => __('Meeting'),
            'maintenance'       => __('Maintenance'),
            'development'       => __('Development'),
            'other'             => __('Other'),
        ];
    }
}
