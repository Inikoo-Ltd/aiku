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
use App\Models\Fulfilment\PalletReturn;

class SendNewPalletReturnCustomerNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;


    public function handle(PalletReturn $palletReturn): void
    {
        /** @var Outbox $outbox */
        $outbox = $palletReturn->fulfilment->shop->outboxes()->where('code', OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->value)->first();

        $customer = $palletReturn->fulfilmentCustomer->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'pallet_reference' => $palletReturn->reference,
                'date' => $palletReturn->created_at->format('F jS, Y'),
                'pallet_link' => route('grp.org.fulfilments.show.operations.pallet-returns.show', [
                    $palletReturn->organisation->slug,
                    $palletReturn->fulfilment->slug,
                    $palletReturn->slug
                ]),
                'customer_link' => route('grp.org.fulfilments.show.crm.customers.show', [
                    $palletReturn->organisation->slug,
                    $palletReturn->fulfilment->slug,
                    $palletReturn->fulfilmentCustomer->slug
                ]),
            ]
        );
    }

}
