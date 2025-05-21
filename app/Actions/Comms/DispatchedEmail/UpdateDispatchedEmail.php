<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\Models\Comms\DispatchedEmail;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateDispatchedEmail extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(DispatchedEmail $dispatchedEmail, array $modelData): DispatchedEmail
    {
        return $this->update($dispatchedEmail, $modelData, ['data']);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("mail.edit");
    }

    public function rules(): array
    {
        $rules = [
            'provider_dispatch_id' => ['sometimes', 'required', 'string'],
            'state'  => ['sometimes', 'required', Rule::enum(DispatchedEmailStateEnum::class)]
        ];
        if (!$this->strict) {
            $rules         = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(DispatchedEmail $dispatchedEmail, array $modelData, int $hydratorsDelay = 0, bool $strict = true): DispatchedEmail
    {
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($dispatchedEmail->organisation, $modelData);

        return $this->handle($dispatchedEmail, $this->validatedData);
    }

    public function jsonResponse(DispatchedEmail $dispatchedEmail): DispatchedEmailsResource
    {
        return new DispatchedEmailsResource($dispatchedEmail);
    }
}
