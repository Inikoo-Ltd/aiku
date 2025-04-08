<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxBuilderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\Comms\Email;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Support\Arr;

class SendPalletDeliveryDeletedNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(PalletDelivery $palletDelivery): void
    {
        /** @var Outbox $outbox */
        $outbox = $palletDelivery->fulfilment->shop->outboxes()->where('code', OutboxCodeEnum::PALLET_DELIVERY_DELETED->value)->first();

        $customer = $palletDelivery->fulfilmentCustomer->customer;
        $subscribedUsers = $outbox->subscribedUsers ?? [];
        // dd($outbox->id);
        foreach ($subscribedUsers as $subscribedUser) {
            if ($subscribedUser->user) {
                $recipient = $subscribedUser->user;
            } else {
                $recipient = $subscribedUser;
            }
            $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
                'is_test'       => false,
                'outbox_id'     => $outbox->id,
                'email_address' => $recipient->email ?? $recipient->external_email,
                'provider'      => DispatchedEmailProviderEnum::SES
            ]);
            $dispatchedEmail->refresh();

            if ($outbox->builder == OutboxBuilderEnum::BLADE) {
                $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
            } else {
                $emailHtmlBody = $outbox->emailOngoingRun?->email?->liveSnapshot?->compiled_layout;
            }

            $this->sendEmailWithMergeTags(
                $dispatchedEmail,
                $outbox->emailOngoingRun->sender(),
                $outbox->emailOngoingRun?->email?->subject,
                $emailHtmlBody,
                '',
                additionalData: [
                    'customer_name' => $customer->name,
                    'pallet_reference' => $palletDelivery->reference,
                    'date' => $palletDelivery->deleted_at->format('F jS, Y'),
                    'pallet_link' => route('grp.org.fulfilments.show.operations.pallet-deliveries.show', [ // TODO: add show deleted pallet delivery
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

}
