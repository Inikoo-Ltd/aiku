<?php

/*
 * author Louis Perez
 * created on 20-04-2026-09h-07m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\UI\Fulfilment;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PalletReturnsBacklogTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case IN_PROCESS   = 'in_process';
    case SUBMITTED    = 'submitted';
    case CONFIRMED    = 'confirmed';
    case PICKING      = 'picking';
    case PICKED       = 'picked';
    case DISPATCHED   = 'dispatched';
}
