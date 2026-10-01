<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\Competitor;

use App\Enums\EnumHelperTrait;

enum MasterAssetCompetitorProductStatusEnum: string
{
    use EnumHelperTrait;

    case SUGGESTED = 'suggested';
    case CONFIRMED = 'confirmed';
    case REJECTED  = 'rejected';
}
