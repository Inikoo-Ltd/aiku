<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Dispatching\PickingIssue;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteItemPickingIssueEnum: string
{
    use EnumHelperTrait;

    case OUT_OF_STOCK = 'out_of_stock';
    case BROKEN = 'broken';

    public static function labels(): array
    {
        return [
            'out_of_stock'   => __('Out of Stock'),
            'broken'         => __('Broken'),
        ];
    }

}
