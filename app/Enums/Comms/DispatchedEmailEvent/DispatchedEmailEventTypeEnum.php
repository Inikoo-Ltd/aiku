<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:10:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Comms\DispatchedEmailEvent;

use App\Enums\EnumHelperTrait;

enum DispatchedEmailEventTypeEnum: string
{
    use EnumHelperTrait;

    case DELIVERY = 'delivery';
    case BOUNCE = 'bounce';

    case COMPLAIN = 'complain';
    case OPEN = 'open';
    case CLICK = 'click';
    case DELIVERY_DELAY = 'delivery-delay';

    case UNSUBSCRIBE = 'unsubscribe';

}
