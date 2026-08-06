<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailTrackingEvent;

use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateClicks;
use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateEmailTracking;
use App\Actions\Comms\DispatchedEmail\Hydrators\DispatchedEmailHydrateReads;
use App\Actions\CRM\TrafficSource\RecordEmailClickTouchpoint;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailTrackingEvent;
use Illuminate\Validation\Rule;

class StoreEmailTrackingEvent extends OrgAction
{
    use WithNoStrictRules;

    public function handle(DispatchedEmail $dispatchedEmail, array $modelData): EmailTrackingEvent
    {
        /** @var EmailTrackingEvent $emailTrackingEvent */
        $emailTrackingEvent = $dispatchedEmail->emailTrackingEvents()->create($modelData);

        DispatchedEmailHydrateEmailTracking::run($dispatchedEmail);
        if ($emailTrackingEvent->type == EmailTrackingEventTypeEnum::CLICKED) {
            DispatchedEmailHydrateClicks::run($dispatchedEmail);

            /* Only mailshot clicks are marketing touches. Transactional sends (order confirmations,
               dispatch notices, invoices) reach the same CLICKED state, and recording those would
               hand the newsletter channel a share of every engaged customer's revenue for clicks
               that are post-purchase service, not acquisition. */
            if ($dispatchedEmail->mailshot) {
                $clickedAt = $emailTrackingEvent->created_at ?? now();

                foreach ($dispatchedEmail->customers as $customer) {
                    RecordEmailClickTouchpoint::dispatch($customer, $clickedAt, $dispatchedEmail->mailshot);
                }

                foreach ($dispatchedEmail->prospects as $prospect) {
                    RecordEmailClickTouchpoint::dispatch($prospect, $clickedAt, $dispatchedEmail->mailshot);
                }
            }
        } elseif ($emailTrackingEvent->type == EmailTrackingEventTypeEnum::OPENED) {
            DispatchedEmailHydrateReads::run($dispatchedEmail);
        }


        return $emailTrackingEvent;
    }

    public function rules(): array
    {
        $rules = [
            'type'       => ['required', Rule::enum(EmailTrackingEventTypeEnum::class)],
            'data'       => ['sometimes', 'array'],
            'created_at' => ['required', 'date'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(DispatchedEmail $dispatchedEmail, array $modelData, int $hydratorsDelay = 0, bool $strict = true): EmailTrackingEvent
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($dispatchedEmail->outbox->organisation, $modelData);

        return $this->handle($dispatchedEmail, $this->validatedData);
    }
}
