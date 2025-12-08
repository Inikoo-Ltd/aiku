<?php

/*
 * author Arya Permana - Kirin
 * created on 19-11-2024-16h-54m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\SalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\SalesChannel;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateSalesChannel extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private SalesChannel $salesChannel;

    public function handle(SalesChannel $salesChannel, array $modelData): SalesChannel
    {
        return $this->update($salesChannel, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'sometimes',
                'alpha_dash',
                'max:16',
                new IUnique(
                    table: 'sales_channels',
                    extraConditions: [
                        [
                            'column' => 'group_id',
                            'value' => $this->group->id,
                        ],
                        [
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $this->salesChannel->id,
                        ],
                    ]
                ),

            ],
            'name' => [
                'sometimes',
                'string',
                new IUnique(
                    table: 'sales_channels',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $this->salesChannel->id,
                        ],
                    ]
                ),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];

        if (! $this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(SalesChannel $salesChannel, ActionRequest $request): SalesChannel
    {
        $this->initialisationFromGroup($salesChannel->group, $request);

        return $this->handle($salesChannel, $this->validatedData);
    }

    public function action(SalesChannel $salesChannel, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): SalesChannel
    {
        if (! $audit) {
            SalesChannel::disableAuditing();
        }

        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->salesChannel = $salesChannel;
        $this->initialisationFromGroup($salesChannel->group, $modelData);

        return $this->handle($salesChannel, $this->validatedData);
    }
}
