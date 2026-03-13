<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 12 Mar 2026 11:22:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\Comms\Traits\WithSendSubscribersOutboxEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Dispatching\DeliveryNote;

class SendUnDispatchedOrderEmailToSubscribers extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithSendSubscribersOutboxEmail;

    public function handle(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->shop->type === ShopTypeEnum::EXTERNAL) {
            return;
        }

        /** @var Outbox $outbox */
        $outbox = $deliveryNote->shop->outboxes()->where('code', OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED->value)->first();

        $customer = $deliveryNote->customer;

        $this->sendOutboxEmailToSubscribers(
            $outbox,
            additionalData: [
                'customer_name' => $customer->name,
                'order_reference' => $deliveryNote->reference,
                'undispatched_date' => now()->format('F jS, Y'),
                'customer_link' => route('grp.org.shops.show.crm.customers.show', [
                    $deliveryNote->organisation->slug,
                    $deliveryNote->shop->slug,
                    $customer->slug
                ]),
                'delivery_note_reference' => $deliveryNote->reference,
                'delivery_note_link' => route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
                    $deliveryNote->organisation->slug,
                    $deliveryNote->warehouse->slug,
                    $deliveryNote->slug
                ]),
            ]
        );
    }
}
