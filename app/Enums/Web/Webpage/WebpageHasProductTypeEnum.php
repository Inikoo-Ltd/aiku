<?php
/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-11h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Web\Webpage;

use App\Enums\EnumHelperTrait;

enum WebpageHasProductTypeEnum: string
{
    use EnumHelperTrait;

    case DIRECT    = 'direct';
    case INDIRECT  = 'indirect';

    public static function labels(): array
    {
        return [
            'direct' => __('Direct'),
            'indirect'      => __('Indirect'),
        ];
    }
}
