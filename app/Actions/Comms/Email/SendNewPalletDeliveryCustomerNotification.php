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

class SendNewPalletDeliveryCustomerNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;

    public function handle(PalletDelivery $palletDelivery): void
    {
        /** @var Outbox $outbox */
        $outbox = $palletDelivery->fulfilment->shop->outboxes()->where('code', OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->value)->first();

        $customer = $palletDelivery->fulfilmentCustomer->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'pallet_reference' => $palletDelivery->reference,
                'date' => $palletDelivery->created_at->format('F jS, Y'),
                'pallet_link' => route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [
                    $palletDelivery->organisation->slug,
                    $palletDelivery->fulfilment->slug,
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
