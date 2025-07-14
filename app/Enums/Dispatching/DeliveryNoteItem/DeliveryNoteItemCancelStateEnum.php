<?php
/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-17h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Dispatching\DeliveryNoteItem;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteItemCancelStateEnum: string
{
    use EnumHelperTrait;


    case UNASSIGNED = 'unassigned';
    case QUEUED = 'queued';
    case HANDLING = 'handling';
    case RETURNED = 'returned';

    public static function labels(): array
    {
        return [
            'unassigned'       => __('Unassigned'),
            'queued'           => __('In Queue'),
            'handling'         => __('Handling'),
            'returned'         => __('Returned'),
        ];
    }
}
