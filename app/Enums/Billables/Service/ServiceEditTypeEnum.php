<?php

/*
 * author Arya Permana - Kirin
 * created on 04-02-2025-15h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Billables\Service;

use App\Enums\EnumHelperTrait;

enum ServiceEditTypeEnum: string
{
    use EnumHelperTrait;

    case QUANTITY = 'quantity';
    case NET = 'net';

    public static function labels(): array
    {
        return [
            'quantity' => __('Quantity'),
            'net' => __('Net'),
        ];
    }
}
