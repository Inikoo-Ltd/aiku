<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 15:13:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Packing;

use App\Enums\EnumHelperTrait;

enum PackingEngineEnum: string
{
    use EnumHelperTrait;

    case AIKU = 'aiku';
    case MAYA = 'maya';

    public static function labels(): array
    {
        return [
            'aiku' => __('Aiku'),
            'maya' => __('Maya'),
        ];
    }
}
