<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
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
use App\Models\CRM\Contacter;
use Illuminate\Support\Arr;

class SendNewContacterNotification extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(Contacter $contacter): void
    {
        /** @var Outbox $outbox */
        $outbox = $contacter->shop->outboxes()->where('code', OutboxCodeEnum::NEW_CONTACTER->value)->first();

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
                    'contacter_name' => $contacter->name,
                    'contacter_email' => $contacter->email,
                    'contacter_shop' => $contacter->shop->name,
                    'contacter_organisation' => $contacter->organisation->name,
                    'contacter_phone' => $contacter->phone,
                    'contacter_url' => $contacter->shop->fulfilment ? route('grp.org.fulfilments.show.crm.contacters.show', [
                        $contacter->organisation->slug,
                        $contacter->shop->fulfilment->slug,
                        $contacter->fulfilmentContacter->slug
                    ]) : route('grp.org.shops.show.crm.contacters.show', [
                        $contacter->organisation->slug,
                        $contacter->shop->slug,
                        $contacter->slug
                    ]),
                    'contacter_date' => $contacter->created_at->format('F jS, Y'),
                ]
            );
        }

    }



}
