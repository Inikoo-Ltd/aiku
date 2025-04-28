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
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;

class SendNewPalletReturnCustomerNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(PalletReturn $palletReturn): void
    {
        /** @var Outbox $outbox */
        $outbox = $palletReturn->fulfilment->shop->outboxes()->where('code', OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->value)->first();

        $customer = $palletReturn->fulfilmentCustomer->customer;
        $subscribedUsers = $outbox->subscribedUsers ?? [];

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

    // public string $commandSignature = 'xxx';

    // public function asCommand($command){
    //     $pallet = PalletReturn::find(33);


    //     $this->handle($pallet);
    // }



}
