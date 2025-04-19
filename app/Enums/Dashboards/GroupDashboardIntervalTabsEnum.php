<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\Dashboards;

use App\Enums\EnumHelperTrait;

// todo Delete this
enum GroupDashboardIntervalTabsEnum: string
{
    use EnumHelperTrait;

    case INVOICE_ORGANISATIONS      = 'invoice_organisations';
    case INVOICE_SHOPS      = 'invoice_shops';
    case INVOICE_CATEGORIES = 'invoice_categories';

}
