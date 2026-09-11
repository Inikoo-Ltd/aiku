<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 08 Jul 2026 15:10:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\Catalogue\Packaging;

use App\Enums\EnumHelperTrait;

enum PackagingTypeEnum: string
{
    use EnumHelperTrait;

    case STANDARD = 'standard';
    case ECO = 'eco';
    case PREMIUM = 'premium';
    case GIFT = 'gift';
    case BRANDED = 'branded';

    public static function labels(): array
    {
        return [
            'standard' => __('Standard Packaging'),
            'eco'      => __('Eco Packaging'),
            'premium'  => __('Premium Packaging'),
            'gift'     => __('Gift Packaging'),
            'branded'  => __('Branded Packaging'),
        ];
    }
}
