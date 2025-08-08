<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Dispatching\PickingIssueMessage;

use App\Enums\EnumHelperTrait;

enum PickingIssueMessageTypeEnum: string
{
    use EnumHelperTrait;

    case ISSUER = 'issuer';
    case RESOLVER = 'resolver';

    public static function labels(): array
    {
        return [
            'issuer'          => __('Issuer'),
            'resolver'         => __('Resolver'),
        ];
    }

}
