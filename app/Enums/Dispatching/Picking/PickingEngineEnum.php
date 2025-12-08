<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 05:07:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\Picking;

use App\Enums\EnumHelperTrait;

enum PickingEngineEnum: string
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
