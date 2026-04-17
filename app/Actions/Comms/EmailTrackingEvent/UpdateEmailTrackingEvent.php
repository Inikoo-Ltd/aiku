<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailTrackingEvent;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\EmailTrackingEvent;
use Lorisleiva\Actions\ActionRequest;

class UpdateEmailTrackingEvent extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(EmailTrackingEvent $emailTrackingEvent, array $modelData): EmailTrackingEvent
    {
        return $this->update($emailTrackingEvent, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        $rules = [];
        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(EmailTrackingEvent $emailTrackingEvent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): EmailTrackingEvent
    {
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($emailTrackingEvent->dispatchedEmail->outbox->organisation, $modelData);

        return $this->handle($emailTrackingEvent, $this->validatedData);
    }


}
