<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jan 2025 15:14:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\Space;

use App\Enums\EnumHelperTrait;

enum SpaceStateEnum: string
{
    use EnumHelperTrait;

    case RESERVED = 'reserved';
    case RENTING = 'renting';
    case FINISHED = 'finished';

    public static function labels(): array
    {
        return [
            'reserved' => __('Reserved'),
            'renting' => __('Renting'),
            'finished' => __('Finished'),
        ];
    }
}
