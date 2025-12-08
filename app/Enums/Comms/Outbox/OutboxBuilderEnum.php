<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Dec 2024 22:42:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\Outbox;

use App\Enums\EnumHelperTrait;

enum OutboxBuilderEnum: string
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
