<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 19 Dec 2024 15:05:54 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\Comms\DispatchedEmail\UpdateDispatchedEmail;
use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Actions\CRM\Prospect\UpdateProspectEmailUnsubscribed;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Customer;

class UnsubscribeMailshot
{
    use WithActionUpdate;

    public function handle(DispatchedEmail $dispatchedEmail, ActionRequest $request): DispatchedEmail
    {
        if ($dispatchedEmail->is_test) {
            return $dispatchedEmail;
        }

        $recipient = $dispatchedEmail->recipient;

        if (class_basename($recipient) == 'Prospect') {
            UpdateProspectEmailUnsubscribed::run($recipient, now());
        }

        if (class_basename($recipient) == class_basename(Customer::class)) {
            $modelData = [
                'is_subscribed_to_newsletter' => false,
            ];
            $customerComms = $recipient->comms;
            UpdateCustomerComms::run($customerComms, $modelData, false);
        }

        UpdateDispatchedEmail::run(
            $dispatchedEmail,
            [
                'state'           => DispatchedEmailStateEnum::UNSUBSCRIBED,
                'provoked_unsubscribe' => true

            ]
        );

        $eventData = [
            'type' => EmailTrackingEventTypeEnum::UNSUBSCRIBED,
            'group_id' => $dispatchedEmail->group_id,
            'organisation_id' => $dispatchedEmail->organisation_id,
            'data' => [
                'ipAddress' => $request->ip(),
                'userAgent' => $request->userAgent()
            ]
        ];


        $dispatchedEmail->emailTrackingEvents()->create($eventData);

        $dispatchedEmail->refresh();

        return $dispatchedEmail;
    }

    public function asController(DispatchedEmail $dispatchedEmail, ActionRequest $request): DispatchedEmail
    {
        return $this->handle($dispatchedEmail, $request);
    }

    public function jsonResponse(DispatchedEmail $dispatchedEmail): array
    {
        return [
            'api_response_status' => 200,
            'api_response_data' => [
                'recipient_email' => $dispatchedEmail->emailAddress?->email,
                'recipient_name' => $dispatchedEmail->getName(),
            ]
        ];
    }
}
