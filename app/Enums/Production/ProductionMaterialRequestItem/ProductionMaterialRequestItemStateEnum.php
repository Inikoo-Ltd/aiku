<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ProductionMaterialRequestItem;

use App\Enums\EnumHelperTrait;

enum ProductionMaterialRequestItemStateEnum: string
{
    use EnumHelperTrait;

    case PENDING   = 'pending';
    case DELIVERED = 'delivered';
    case SHORT     = 'short';

    public static function labels($forElements = false): array
    {
        return [
            'pending'   => __('To bring'),
            'delivered' => __('Delivered'),
            'short'     => __("Can't bring"),
        ];
    }
}
