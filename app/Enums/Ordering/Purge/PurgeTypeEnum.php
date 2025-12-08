<?php

/*
 * author Arya Permana - Kirin
 * created on 01-11-2024-10h-33m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\Ordering\Purge;

use App\Enums\EnumHelperTrait;

enum PurgeTypeEnum: string
{
    use EnumHelperTrait;

    case CRON = 'cron';
    case MANUAL = 'manual';
}
