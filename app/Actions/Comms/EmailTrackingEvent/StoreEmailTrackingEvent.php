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
