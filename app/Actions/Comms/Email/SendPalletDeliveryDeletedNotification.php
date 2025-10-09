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
use App\Models\Fulfilment\PalletDelivery;

class SendPalletDeliveryDeletedNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;

    public function handle(PalletDelivery $palletDelivery): void
    {
        /** @var Outbox $outbox */
        $outbox = $palletDelivery->fulfilment->shop->outboxes()->where('code', OutboxCodeEnum::PALLET_DELIVERY_DELETED->value)->first();

        $customer = $palletDelivery->fulfilmentCustomer->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'pallet_reference' => $palletDelivery->reference,
                'date' => $palletDelivery->deleted_at->format('F jS, Y'),
                'pallet_link' => route('grp.org.fulfilments.show.crm.customers.show.deleted_pallet_deliveries.show', [
                    $palletDelivery->organisation->slug,
                    $palletDelivery->fulfilment->slug,
                    $palletDelivery->fulfilmentCustomer->slug,
                    $palletDelivery->slug
                ]),
                'customer_link' => route('grp.org.fulfilments.show.crm.customers.show', [
                    $palletDelivery->organisation->slug,
                    $palletDelivery->fulfilment->slug,
                    $palletDelivery->fulfilmentCustomer->slug
                ]),
            ]
        );

    }

}
