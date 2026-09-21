<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 21 Sep 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\EmailTemplate;

use App\Enums\EnumHelperTrait;

enum EmailTemplateRowTypeEnum: string
{
    use EnumHelperTrait;

    case HEADER = 'header';
    case FOOTER = 'footer';
    case BLOCK  = 'block';

    public static function labels(): array
    {
        return [
            'header' => __('Header'),
            'footer' => __('Footer'),
            'block'  => __('Block'),
        ];
    }
}
