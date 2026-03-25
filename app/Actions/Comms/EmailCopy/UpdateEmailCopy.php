<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailCopy;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\EmailCopy;

class UpdateEmailCopy extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(EmailCopy $emailCopy, array $modelData): EmailCopy
    {
        return $this->update($emailCopy, $modelData);
    }


    public function rules(): array
    {
        $rules = [];

        if (!$this->strict) {
            $rules['subject'] = ['sometimes', 'nullable', 'string'];
            $rules['body']   = ['sometimes', 'required', 'string'];
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(EmailCopy $emailCopy, array $modelData, int $hydratorsDelay = 0, bool $strict = true): EmailCopy
    {
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($emailCopy->dispatchedEmail->outbox->organisation, $modelData);

        return $this->handle($emailCopy, $this->validatedData);
    }


}
