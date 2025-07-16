<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Portfolio;

use App\Enums\EnumHelperTrait;

enum PortfolioPlatformAvailabilityOptionEnum: string
{
    use EnumHelperTrait;

    case USE_EXISTING  = 'use_existing';
    case REPLACE = 'replace';
    case DUPLICATE = 'duplicate';
}
