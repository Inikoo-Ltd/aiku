<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Accounting\ReceivedEInvoice;

use App\Enums\EnumHelperTrait;

enum ReceivedEInvoiceMatchStateEnum: string
{
    use EnumHelperTrait;

    case UNMATCHED = 'unmatched';
    case MATCHED = 'matched';
    case IGNORED = 'ignored';

    public static function labels(): array
    {
        return [
            'unmatched' => __('To match'),
            'matched'   => __('Matched'),
            'ignored'   => __('Ignored'),
        ];
    }
}
