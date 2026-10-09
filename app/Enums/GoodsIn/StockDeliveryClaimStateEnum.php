<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn;

use App\Enums\EnumHelperTrait;

enum StockDeliveryClaimStateEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';
    case SENT = 'sent';
    case CREDIT_RECEIVED = 'credit_received';
    case REJECTED = 'rejected';

    public static function labels(): array
    {
        return [
            'open'            => __('Open'),
            'sent'            => __('Sent to supplier'),
            'credit_received' => __('Credit received'),
            'rejected'        => __('Rejected'),
        ];
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::CREDIT_RECEIVED, self::REJECTED]);
    }
}
