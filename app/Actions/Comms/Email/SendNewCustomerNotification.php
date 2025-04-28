<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-03-2025, Bali, Indonesia
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
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;

class SendNewCustomerNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Customer $customer): void
    {
        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::NEW_CUSTOMER->value)->first();

        $subscribedUsers = $outbox->subscribedUsers;
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

    public string $commandSignature = 'xxxx';

    public function asCommand($command)
    {
        $pallet = Customer::withTrashed()->find(182402);

        $this->handle($pallet);
    }



}
