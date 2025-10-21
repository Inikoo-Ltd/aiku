<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Oct 2025 19:59:06 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\PaymentGatewayLog;

use App\Enums\EnumHelperTrait;

enum PaymentGatewayLogStatusEnum: string
{
    use EnumHelperTrait;

    case PROCESSING = 'processing';
    case OK = 'ok';
    case FAIL = 'fail';
    case NA = 'na';

    public static function labels(): array
    {
        return [
            'processing' => __('Processing'),
            'ok'         => __('Ok'),
            'fail'       => __('Fail'),
            'na'         => __('N/A'),
        ];
    }
}
