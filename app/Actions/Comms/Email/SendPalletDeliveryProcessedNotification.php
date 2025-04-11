<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-03-2025, Bali, Indonesia
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
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;

class SendPalletDeliveryProcessedNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Customer $customer): DispatchedEmail
    {

        $outbox = $customer->shop->outboxes()->where('code', OutboxCodeEnum::PALLET_DELIVERY_PROCESSED->value)->first();
        $outboxDispatch = $customer->shop->outboxes()->where('type', OutboxTypeEnum::CUSTOMER_NOTIFICATION)->first();

        $recipient       = $customer;
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outboxDispatch->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();

        if ($outbox->builder == OutboxBuilderEnum::BLADE) {
            $emailHtmlBody = Arr::get($outbox->emailOngoingRun?->email?->liveSnapshot?->layout, 'blade_template');
        } else {
            $emailHtmlBody = $outbox->emailOngoingRun?->email?->liveSnapshot?->compiled_layout;
        }

        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            ''
        );
    }



}
