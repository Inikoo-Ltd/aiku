<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Nov 2023 12:43:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Transfers\Fetch;

use App\Enums\EnumHelperTrait;

enum FetchTypeEnum: string
{
    use EnumHelperTrait;

    case PROSPECTS         = 'prospects';
    case CUSTOMERS         = 'customers';
    case LOCATIONS         = 'locations';
    case EMPLOYEES         = 'employees';
    case INVOICES          = 'invoices';
    case STOCKS            = 'stocks';
    case ORDERS            = 'orders';
    case SUPPLIER_PRODUCTS = 'supplier_products';
    case BASE              = 'base';

}
