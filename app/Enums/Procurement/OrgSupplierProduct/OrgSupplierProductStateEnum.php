<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:23:30 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Procurement\OrgSupplierProduct;

use App\Enums\EnumHelperTrait;

enum OrgSupplierProductStateEnum: string
{
    use EnumHelperTrait;

    case ACTIVE = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';
}
