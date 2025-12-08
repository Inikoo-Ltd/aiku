<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2024 14:27:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrderDispatchedEmail extends FetchAurora
{
    protected function parseModel(): void
    {
        $order = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Order Sent Email Order Key'});
        if (! $order) {
            // print "Error no order\n";
            return;
        }
        $dispatchedEmail = $this->parseDispatchedEmail($this->organisation->id.':'.$this->auroraModelData->{'Order Sent Email Email Tracking Key'});
        if (! $dispatchedEmail) {
            // print "Error no dispatchedEmail\n";
            return;
        }

        $outboxCode = match ($this->auroraModelData->{'Order Sent Email Type'}) {
            'Order Notification' => OutboxCodeEnum::ORDER_CONFIRMATION,
            'Dispatch Notification' => OutboxCodeEnum::DELIVERY_CONFIRMATION,
            'Basket Reminder 1', 'Basket Reminder 2', 'Basket Reminder 3' => OutboxCodeEnum::BASKET_PUSH,
            default => null
        };
        if ($outboxCode == null) {
            // print "Error no outbox type\n";
            return;
        }

        $outbox = $order->shop->outboxes()->where('code', $outboxCode)->first();
        if (! $outbox) {
            // print "Error no outbox with code $outboxCode->value  \n";
            return;
        }

        $this->parsedData['order'] = $order;
        $this->parsedData['dispatchedEmail'] = $dispatchedEmail;
        $this->parsedData['outbox'] = $outbox;

        $this->parsedData['modelHasDispatchedEmail'] = [
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order Sent Email Bridge Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Order Sent Email Bridge')
            ->where('Order Sent Email Bridge Key', $id)->first();
    }
}
