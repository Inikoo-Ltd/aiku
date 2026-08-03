<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\OutboxHasSubscribers;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\OutBoxHasSubscriber;

class UpdateOutboxHasSubscriber extends OrgAction
{
    use WithNoStrictRules;
    use WithActionUpdate;

    public function handle(OutBoxHasSubscriber $outBoxHasSubscriber, array $modelData): OutBoxHasSubscriber
    {
        return $this->update($outBoxHasSubscriber, $modelData);
    }


    public function rules(): array
    {
        $rules = [
            'external_email' => ['sometimes', 'email'],
        ];
        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function action(OutBoxHasSubscriber $outBoxHasSubscriber, array $modelData, int $hydratorsDelay = 0, bool $strict = true): OutBoxHasSubscriber
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($outBoxHasSubscriber->organisation, $modelData);

        return $this->handle($outBoxHasSubscriber, $this->validatedData);
    }

}
