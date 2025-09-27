<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailCopy;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailCopy;

class StoreEmailCopy extends OrgAction
{
    use WithNoStrictRules;

    public function handle(DispatchedEmail $dispatchedEmail, array $modelData): EmailCopy
    {

        $modelData['body']    = base64_encode($modelData['body'] ?? '');
        $modelData['is_body_encoded'] = true;

        /** @var EmailCopy $emailCopy */
        $emailCopy = $dispatchedEmail->emailCopy()->create($modelData);
        return $emailCopy;
    }

    public function rules(): array
    {
        $rules = [
            'subject' => ['nullable','string'],
            'body'    => ['required', 'string'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(DispatchedEmail $dispatchedEmail, array $modelData, int $hydratorsDelay = 0, bool $strict = true): EmailCopy
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($dispatchedEmail->organisation, $modelData);

        return $this->handle($dispatchedEmail, $this->validatedData);
    }


}
