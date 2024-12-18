<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:20:49 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Discounts\OfferComponent;

use App\Enums\EnumHelperTrait;

enum OfferComponentStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in-process';
    case ACTIVE     = 'active';
    case FINISHED   = 'finished';
    case SUSPENDED  = 'suspended';


}
