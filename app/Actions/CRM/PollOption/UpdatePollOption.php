<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 18:11:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\PollOption;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\PollOption;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdatePollOption extends OrgAction
{
    use WithWebAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;



    private PollOption $pollOption;

    public function handle(PollOption $pollOption, array $modelData): PollOption
    {
        $pollOption = $this->update($pollOption, $modelData);
        //todo put hydrators here if in_registration|in_registration_required|in_iris|in_iris_required has changed
        return $pollOption;
    }

    public function rules(): array
    {
        $rules = [
            'value'                     => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'poll_options',
                    extraConditions: [
                        ['column' => 'poll_id', 'value' => $this->pollOption->poll_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->pollOption->id
                        ]
                    ]
                ),
            ],
            'label'                    => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'poll_options',
                    extraConditions: [
                        ['column' => 'poll_id', 'value' => $this->pollOption->poll_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->pollOption->id
                        ]
                    ]
                ),
            ],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(PollOption $pollOption, ActionRequest $request): PollOption
    {
        $this->initialisationFromShop($pollOption->poll->shop, $request);

        return $this->handle($pollOption, $this->validatedData);
    }

    public function action(PollOption $pollOption, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): PollOption
    {
        $this->strict = $strict;
        if (!$audit) {
            PollOption::disableAuditing();
        }
        $this->asAction       = true;
        $this->pollOption           = $pollOption;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($pollOption->poll->shop, $modelData);

        return $this->handle($pollOption, $this->validatedData);
    }

}
