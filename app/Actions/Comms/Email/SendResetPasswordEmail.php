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
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Comms\Outbox;
use App\Models\CRM\WebUser;

class SendResetPasswordEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;

    private Email $email;

    public function handle(WebUser $webUser, array $modelData): ?DispatchedEmail
    {
        /** @var Outbox $outbox */
        $outbox = $webUser->shop->outboxes()->where('code', 'password_reminder')->first();
        if ($outbox->state != OutboxStateEnum::ACTIVE) {
            return null;
        }

        $recipient       = $webUser;
        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outbox->id,
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
            '',
            passwordToken: $modelData['url']
        );
    }


}
