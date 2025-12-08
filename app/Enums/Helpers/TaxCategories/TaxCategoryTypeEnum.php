<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 20:15:32 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TaxCategories;

use App\Enums\EnumHelperTrait;

enum TaxCategoryTypeEnum: string
{
    use EnumHelperTrait;

    case STANDARD = 'standard';
    case REDUCED = 'reduced';
    case OUTSIDE = 'outside';
    case EXEMPT = 'exempt';
    case EU_VTC = 'eu_vtc';
    case SPECIAL = 'special';
    case REDUCED_SPECIAL = 'reduced_special';
    case LEGACY = 'legacy';

}
