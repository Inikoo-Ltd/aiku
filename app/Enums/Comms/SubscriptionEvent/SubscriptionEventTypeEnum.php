<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 12:51:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\SubscriptionEvent;

use App\Enums\EnumHelperTrait;

enum SubscriptionEventTypeEnum: string
{
    use EnumHelperTrait;

    case SUBSCRIBE = 'subscribe';
    case UNSUBSCRIBE = 'unsubscribe';

}
