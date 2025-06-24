<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:01:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Poll;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePolls;
use App\Actions\CRM\Poll\Hydrate\PollHydrateCustomers;
use App\Actions\CRM\PollOption\DeletePollOptions;
use App\Actions\CRM\PollOption\StorePollOption;
use App\Actions\CRM\PollOption\UpdatePollOption;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\Poll;
use App\Models\CRM\PollOption;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePoll extends OrgAction
{
    // TODO: raul fix the permissions
    // use WithCRMAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;


    /**
     * @var \App\Models\CRM\Poll
     */
    private Poll $poll;

    public function handle(Poll $poll, array $modelData): Poll
    {
        $type = Arr::pull($modelData, 'type.type');
        $options = Arr::pull($modelData, 'type.poll_options', []);
        if ($type) {
            data_set($modelData, 'type', $type);
        }
        $poll = $this->update($poll, $modelData);
        if ($poll->type == PollTypeEnum::OPTION && $options) {
            $oldOptions = $poll->pollOptions;
            foreach ($options ?? [] as $index => $option) {
                if (isset($option['id'])) {
                    UpdatePollOption::make()->action(
                        PollOption::findOrFail($option['id']),
                        [
                            'label' => $option['label'],
                        ]
                    );
                    $oldOptions = $oldOptions->reject(
                        fn (PollOption $pollOption) => $pollOption->id == $option['id']
                    );
                } else {
                    StorePollOption::make()->action(
                        $poll,
                        [
                            'value' => $poll->shop->id . $poll->id . $index,
                            'label' => $option['label'],
                        ]
                    );
                }
            }
            if (!empty($oldOptions)) {
                foreach ($oldOptions as $oldOption) {
                    DeletePollOptions::run(
                        $oldOption,
                        true
                    );
                }
            }
        }

        ShopHydratePolls::dispatch($poll->shop);
        PollHydrateCustomers::dispatch($poll);
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
            'type'                => [
                'sometimes',
                'array',
            ],
            'type.type'        => [
                'sometimes',
                Rule::enum(PollTypeEnum::class)
            ],
            'type.poll_options'        => [
                'sometimes',
                'array',
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
        $this->poll          = $poll;
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
