<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Comms\Email;
use App\Models\CRM\Customer;

class SendNewCustomerNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendSubscribersOutboxEmail;

    private Email $email;

    public function handle(Customer $customer): void
    {
        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::NEW_CUSTOMER->value)->first();

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_shop' => $customer->shop->name,
                'customer_organisation' => $customer->organisation->name,
                'customer_url' => $customer->shop->fulfilment ? route('grp.org.fulfilments.show.crm.customers.show', [
                    $customer->organisation->slug,
                    $customer->shop->fulfilment->slug,
                    $customer->fulfilmentCustomer->slug
                ]) : route('grp.org.shops.show.crm.customers.show', [
                    $customer->organisation->slug,
                    $customer->shop->slug,
                    $customer->slug
                ]),
                'customer_register_date' => $customer->created_at->format('F jS, Y')
            ]
        );
    }


}
