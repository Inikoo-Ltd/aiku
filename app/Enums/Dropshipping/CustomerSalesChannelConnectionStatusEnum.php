<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:12:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum CustomerSalesChannelConnectionStatusEnum: string
{
    use EnumHelperTrait;

    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
    case PENDING = 'pending';
    case NO_APPLICABLE = 'no_applicable';

    public static function labels(): array
    {
        return [
            'connected'     => __('Connected'),
            'disconnected'  => __('Disconnected'),
            'error'         => __('Error'),
            'pending'       => __('Pending'),
            'no_applicable' => __('N/A'),
        ];
    }
}
