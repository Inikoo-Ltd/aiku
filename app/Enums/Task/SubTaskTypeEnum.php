<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Task;

use App\Enums\EnumHelperTrait;

enum SubTaskTypeEnum: string
{
    use EnumHelperTrait;

    case MASTER_PRICE_DRIFT = 'master-price-drift';

    public static function labels(): array
    {
        return [
            'master-price-drift' => __('Price does not match master'),
        ];
    }
}
