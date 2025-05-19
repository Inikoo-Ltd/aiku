<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:01:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Poll;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Poll;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdatePoll extends OrgAction
{
    use WithWebAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;


    /**
     * @var \App\Models\CRM\Poll
     */
    private Poll $poll;

    public function handle(Poll $poll, array $modelData): Poll
    {
        $poll = $this->update($poll, $modelData);
        //todo put hydrators here if in_registration|in_registration_required|in_iris|in_iris_required has changed
        return $poll;
    }

    public function rules(): array
    {
        $rules = [
            'name'                     => [
                'sometimes',
                'required',
                'max:255',
                'string',
                new IUnique(
                    table: 'polls',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->poll->id
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
                    table: 'polls',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->poll->id
                        ]
                    ]
                ),
            ],
            'in_registration'          => ['sometimes', 'boolean'],
            'in_registration_required' => ['sometimes', 'boolean'],
            'in_iris'                  => ['sometimes', 'boolean'],
            'in_iris_required'         => ['sometimes', 'boolean'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(Poll $poll, ActionRequest $request): Poll
    {
        $this->initialisationFromShop($poll->shop, $request);

        return $this->handle($poll, $this->validatedData);
    }

    public function action(Poll $poll, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Poll
    {
        $this->strict = $strict;
        if (!$audit) {
            Poll::disableAuditing();
        }
        $this->asAction       = true;
        $this->poll           = $poll;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($poll->shop, $modelData);

        return $this->handle($poll, $this->validatedData);
    }

}
