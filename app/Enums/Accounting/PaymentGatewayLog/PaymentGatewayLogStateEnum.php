<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Oct 2025 20:01:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\PaymentGatewayLog;

use App\Enums\EnumHelperTrait;

enum PaymentGatewayLogStateEnum: string
{
    use EnumHelperTrait;

    case RECEIVED = 'received';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';


}
