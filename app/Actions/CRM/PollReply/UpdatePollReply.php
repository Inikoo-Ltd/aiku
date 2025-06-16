<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 19:22:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\PollReply;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\PollReply;
use Illuminate\Validation\Rule;

class UpdatePollReply extends OrgAction
{
    use WithWebAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;


    private PollReply $pollReply;

    public function handle(PollReply $pollReply, array $modelData): PollReply
    {
        $pollReply = $this->update($pollReply, $modelData);
        //todo put hydrators here if in_registration|in_registration_required|in_iris|in_iris_required has changed
        return $pollReply;
    }

    public function rules(): array
    {
        $rules = [
        ];

        if ($this->pollReply->poll->type == PollTypeEnum::OPEN_QUESTION) {
            $rules['value'] = [
                'sometimes',
                'required',
                'string',
                'max:10000'
            ];
        } else {
            $rules['poll_option_id'] = [
                'sometimes',
                'required',
                Rule::exists('poll_options', 'id')->where(function ($query) {
                    $query->where('poll_id', $this->pollReply->poll_id);
                })
            ];
        }

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }



    public function action(PollReply $pollReply, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PollReply
    {
        $this->strict = $strict;
        $this->asAction       = true;
        $this->pollReply           = $pollReply;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($pollReply->poll->shop, $modelData);

        return $this->handle($pollReply, $this->validatedData);
    }

}
