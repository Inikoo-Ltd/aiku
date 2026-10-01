<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Actions\Comms\Email\SendPreOrderUpdateEmail;
use App\Models\Ordering\PreOrder;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * When the date slips the customer is told the new one, with their cancellation options.
 */
class UpdatePreOrderDispatchDates
{
    use AsObject;

    public function handle(PreOrder $preOrder, string $from, string $to, ?string $reason = null): PreOrder
    {
        $preOrder->update([
            'estimated_dispatch_from' => Carbon::parse($from)->toDateString(),
            'estimated_dispatch_to'   => Carbon::parse($to)->toDateString(),
        ]);

        SendPreOrderUpdateEmail::dispatch($preOrder, SendPreOrderUpdateEmail::DISPATCH_DATE_CHANGED, ['reason' => $reason])->afterCommit();

        return $preOrder;
    }
}
