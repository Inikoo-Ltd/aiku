<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 01-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Poll\UI;

use App\Actions\CRM\Poll\UpdatePoll;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\CRM\Poll;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaPoll extends RetinaAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    /**
     * @var \App\Models\CRM\Poll
     */
    private Poll $poll;

    public function handle(Poll $poll, array $modelData): Poll
    {

        return UpdatePoll::make()->action(
            $poll,
            $modelData
        );
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
            'type'                     => [
                'sometimes',
                'array',
            ],
            'type.type'                => [
                'sometimes',
                Rule::enum(PollTypeEnum::class)
            ],
            'type.poll_options'        => [
                'sometimes',
                'array',
            ],
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
        $this->initialisation($request);

        return $this->handle($poll, $this->validatedData);
    }

}
