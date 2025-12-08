<?php

/*
 * author Arya Permana - Kirin
 * created on 01-11-2024-10h-31m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\Ordering\Purge;

use App\Enums\EnumHelperTrait;

enum PurgeStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case PURGING = 'purging';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';
}
