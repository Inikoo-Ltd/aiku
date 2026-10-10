<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\MasterAsset;

use App\Enums\EnumHelperTrait;

enum MasterAssetPriceTipKindEnum: string
{
    use EnumHelperTrait;

    case OVERSTOCKED   = 'overstocked';
    case RUNNING_OUT   = 'running_out';
    case BEHIND_FAMILY = 'behind_family';

    public static function labels(): array
    {
        return [
            'overstocked'   => __('Over 18 months of stock, sales falling'),
            'running_out'   => __('Running out, selling faster'),
            'behind_family' => __('Selling worse than its family'),
        ];
    }
}
