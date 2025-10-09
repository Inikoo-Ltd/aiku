<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Pallet;

class SendPalletDeletedNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;

    public function handle(Pallet $pallet): void
    {

        /** @var Outbox $outbox */
        $outbox = $pallet->fulfilment->shop->outboxes()->where('code', OutboxCodeEnum::PALLET_DELETED->value)->first();

        $customer = $pallet->fulfilmentCustomer->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'pallet_reference' => $pallet->reference,
                'date' => $pallet->deleted_at->format('F jS, Y'),
                'pallet_link' => route('grp.org.fulfilments.show.crm.customers.show.deleted_pallets.show', [
                    $pallet->organisation->slug,
                    $pallet->fulfilment->slug,
                    $pallet->fulfilmentCustomer->slug,
                    $pallet->slug
                ]),
                'customer_link' => route('grp.org.fulfilments.show.crm.customers.show', [
                    $pallet->organisation->slug,
                    $pallet->fulfilment->slug,
                    $pallet->fulfilmentCustomer->slug
                ]),
            ]
        );


    }


}
