<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 19:10:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\PollReply;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\Poll;
use App\Models\CRM\PollReply;
use Illuminate\Validation\Rule;

class StorePollReply extends OrgAction
{
    use WithCRMEditAuthorisation;
    use WithNoStrictRules;


    private Poll $poll;

    public function handle(Poll $poll, array $modelData): PollReply
    {
        return $poll->pollReplies()->create($modelData);

    }

    public function rules(): array
    {
        $rules = [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(function ($query) {
                    $query->where('shop_id', $this->poll->shop_id);
                })
            ]
        ];

        if ($this->poll->type == PollTypeEnum::OPEN_QUESTION) {
            $rules['value'] = [
                'required',
                'string',
                'max:10000'
            ];
        } else {
            $rules['poll_option_id'] = [
                'required',
                Rule::exists('poll_options', 'id')->where(function ($query) {
                    $query->where('poll_id', $this->poll->id);
                })
            ];
        }


        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    public function action(Poll $poll, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PollReply
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->poll         = $poll;
        $this->initialisationFromShop($poll->shop, $modelData);

        return $this->handle($poll, $this->validatedData);
    }

}
