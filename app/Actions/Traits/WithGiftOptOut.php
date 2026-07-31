<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

trait WithGiftOptOut
{
    protected function isGiftOptedOut(Order $order): bool
    {
        return (bool)Arr::get($order->customer?->settings, 'is_gift_opted_out', false);
    }
}
