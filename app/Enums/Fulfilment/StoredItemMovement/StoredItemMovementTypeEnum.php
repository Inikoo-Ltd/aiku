<?php

/*
 * author Arya Permana - Kirin
 * created on 06-02-2025-14h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Fulfilment\StoredItemMovement;

use App\Enums\EnumHelperTrait;

enum StoredItemMovementTypeEnum: string
{
    use EnumHelperTrait;

    case RECEIVED = 'received';
    case PICKED = 'picked';
    case AUDIT_ADDITION = 'audit_addition';
    case AUDIT_SUBTRACTION = 'audit_subtraction';
    case MOVE_OUT = 'move_out';
    case MOVE_IN = 'move_in';
}
