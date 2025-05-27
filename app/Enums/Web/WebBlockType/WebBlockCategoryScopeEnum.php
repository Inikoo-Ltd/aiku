<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jul 2023 15:53:24 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\WebBlockType;

use App\Enums\EnumHelperTrait;

enum WebBlockCategoryScopeEnum: string
{
    use EnumHelperTrait;


    case FAMILY  = 'family';
    case PRODUCT  = 'product';
    case DEPARTMENT  = 'department';
    case SUB_DEPARTMENT  = 'sub_department';



}
