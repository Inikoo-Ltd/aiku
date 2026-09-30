<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\MasterAsset;

use App\Enums\EnumHelperTrait;

enum MasterAssetPriceTipStatusEnum: string
{
    use EnumHelperTrait;

    case OPEN      = 'open';
    case APPLIED   = 'applied';
    case DISMISSED = 'dismissed';
    case EXPIRED   = 'expired';
}
