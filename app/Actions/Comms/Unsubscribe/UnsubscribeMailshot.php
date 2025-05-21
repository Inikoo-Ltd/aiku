<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 19 Dec 2024 15:05:54 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\Comms\DispatchedEmail\UpdateDispatchedEmail;
use App\Actions\CRM\Prospect\UpdateProspectEmailUnsubscribed;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\DispatchedEmailEvent\DispatchedEmailEventTypeEnum;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\Models\Comms\DispatchedEmail;
use Lorisleiva\Actions\ActionRequest;

class UnsubscribeMailshot
{
    use WithActionUpdate;

    public function handle(DispatchedEmail $dispatchedEmail, ActionRequest $request): DispatchedEmail
    {
        if ($dispatchedEmail->is_test) {
            return $dispatchedEmail;
        }

        $recipient = $dispatchedEmail->mailshotRecipient->recipient;
        if (class_basename($recipient) == 'Prospect') {
            UpdateProspectEmailUnsubscribed::run($recipient, now());
        }

        UpdateDispatchedEmail::run(
            $dispatchedEmail,
            [
                'state'           => DispatchedEmailStateEnum::UNSUBSCRIBED,
                'date'            => now(),
                'is_unsubscribed' => true

            ]
        );

        $eventData = [
            'type' => DispatchedEmailEventTypeEnum::UNSUBSCRIBE,
            'date' => now(),
            'data' => [
                'ipAddress' => $request->ip(),
                'userAgent' => $request->userAgent()
            ]
        ];


        $dispatchedEmail->events()->create($eventData);


        return $this->update($dispatchedEmail, ['state' => DispatchedEmailStateEnum::UNSUBSCRIBED]);
    }

    public function asController(DispatchedEmail $dispatchedEmail, ActionRequest $request): DispatchedEmail
    {
        return $this->handle($dispatchedEmail, $request);
    }

    public function jsonResponse(DispatchedEmail $dispatchedEmail): array
    {
        return DispatchedEmailsResource::make($dispatchedEmail)->getArray();
    }

}
