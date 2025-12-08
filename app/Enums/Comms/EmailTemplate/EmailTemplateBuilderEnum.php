<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 13:09:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\EmailTemplate;

use App\Enums\EnumHelperTrait;

enum EmailTemplateBuilderEnum: string
{
    use EnumHelperTrait;

    case UNLAYER = 'unlayer';
    case BEEFREE = 'beefree';
    case BLADE = 'blade';

    public static function labels(): array
    {
        return [
            'unlayer' => __('Unlayer'),
            'beefree' => __('BeeFree'),
            'blade' => __('Blade (Hard coded)'),
        ];
    }
}
