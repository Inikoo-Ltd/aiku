<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:55 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Dashboard;

use App\Enums\EnumHelperTrait;

enum DashboardDataType: string
{
    use EnumHelperTrait;


    case NUMBER = 'number';
    case NUMBER_MINIFIED = 'number_minified';
    case CURRENCY = 'currency';
    case CURRENCY_MINIFIED = 'currency_minified';
    case PERCENTAGE = 'percentage';
    case DELTA_LAST_YEAR = 'delta_last_year';


}
