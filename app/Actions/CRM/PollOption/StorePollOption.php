<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 17:57:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\PollOption;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class StorePollOption extends OrgAction
{
    use WithWebAuthorisation;
    use WithNoStrictRules;


    private Poll $poll;

    public function handle(Poll $poll, array $modelData): PollOption
    {
        data_set($modelData, 'group_id', $poll->group_id);
        data_set($modelData, 'organisation_id', $poll->organisation_id);
        data_set($modelData, 'shop_id', $poll->shop_id);

        $pollOption = $poll->pollOptions()->create($modelData);

        //todo add Poll,Store,Org,Group hydrators here

        return $pollOption;
    }

    public function rules(): array
    {
        $rules = [
            'value' => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'poll_options',
                    extraConditions: [
                        ['column' => 'poll_id', 'value' => $this->poll->id],
                    ]
                ),
            ],
            'label' => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'poll_options',
                    extraConditions: [
                        ['column' => 'poll_id', 'value' => $this->poll->id],
                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function asController(Poll $poll, ActionRequest $request): PollOption
    {
        $this->initialisationFromShop($poll->shop, $request);

        return $this->handle($poll, $this->validatedData);
    }

    public function action(Poll $poll, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): PollOption
    {
        if (!$audit) {
            PollOption::disableAuditing();
        }
        $this->poll           = $poll;
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($poll->shop, $modelData);

        return $this->handle($poll, $this->validatedData);
    }

}
