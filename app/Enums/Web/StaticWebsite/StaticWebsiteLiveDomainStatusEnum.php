<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Web\StaticWebsite;

use App\Enums\EnumHelperTrait;

enum StaticWebsiteLiveDomainStatusEnum: string
{
    use EnumHelperTrait;

    case AIKU = 'aiku';
    case ELSEWHERE = 'elsewhere';
    case UNREACHABLE = 'unreachable';

    public static function labels(): array
    {
        return [
            'aiku'        => __('Served by Aiku'),
            'elsewhere'   => __('Another website'),
            'unreachable' => __('Not answering'),
        ];
    }

    public static function icons(): array
    {
        return [
            'aiku'        => [
                'tooltip' => __('The live domain shows the pages stored in Aiku'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-600',
                'color'   => 'green',
            ],
            'elsewhere'   => [
                'tooltip' => __('The live domain shows a website hosted somewhere else, not the pages in Aiku'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500',
                'color'   => 'red',
            ],
            'unreachable' => [
                'tooltip' => __('The live domain does not answer'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-amber-500',
                'color'   => 'amber',
            ],
        ];
    }
}
