<?php

/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-13h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Enums\Web\ModelHasContent;

use App\Enums\EnumHelperTrait;

enum ModelHasContentTypeEnum: string
{
    use EnumHelperTrait;

    case FAQ = 'faq';
    case INFORMATION = 'information';
    case REVIEW = 'review';

    public static function labels(): array
    {
        return [
            'faq'         => __('frequently asked questions'),
            'information' => __('information'),
            'review'      => __('review'),
        ];
    }
}
