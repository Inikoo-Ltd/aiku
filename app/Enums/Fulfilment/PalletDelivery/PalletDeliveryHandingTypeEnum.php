<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 May 2024 19:45:40 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletDelivery;

use App\Enums\EnumHelperTrait;

enum PalletDeliveryHandingTypeEnum: string
{
    use EnumHelperTrait;
    case COLLECTION = 'collection';
    case SHIPPING = 'shipping';

}
