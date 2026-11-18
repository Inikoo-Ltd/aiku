<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\ProductionMaterialRequest;

use App\Enums\EnumHelperTrait;

enum ProductionMaterialRequestSourceEnum: string
{
    use EnumHelperTrait;

    case MATERIALS_CHECK = 'materials_check';
    case MANUAL = 'manual';
}
