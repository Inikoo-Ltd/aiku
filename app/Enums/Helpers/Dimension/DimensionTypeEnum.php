<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Apr 2023 12:27:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Dimension;

use App\Enums\EnumHelperTrait;

enum DimensionTypeEnum: string
{
    use EnumHelperTrait;

    case RECTANGULAR = 'rectangular';
    case SHEET   = 'sheet';
    case CILINDER     = 'cilinder';
    case SPHERE     = 'sphere';
    case STRING     = 'string';
}
