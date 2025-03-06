<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;

class SendCustomerWelcomeEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Customer $customer): DispatchedEmail
    {
        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::REGISTRATION->value)->first();
        $outboxDispatch = $customer->shop->outboxes()->where('type', OutboxTypeEnum::CUSTOMER_NOTIFICATION)->first();

        $recipient       = $customer;
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outboxDispatch->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();

        $emailHtmlBody = $outbox->emailOngoingRun->email->liveSnapshot->compiled_layout;

        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            ''
        );
    }

    public string $commandSignature = 'x';

    public function asCommand($command)
    {
        $c = Customer::first();
        // $outbox = $c->shop->outboxes()->where('code', OutboxCodeEnum::NEW_CUSTOMER->value)->first();
        // // 'external_email' => 'dev@aw-advantage.com',

        // StoreOutboxHasSubscriber::make()->action(
        //     Outbox::find(158),
        //     [
        //         'external_email' => 'artha@aw-advantage.com',
        //     ]
        // );



        $this->handle($c);
    }
}
