<?php

/*
 * author Arya Permana - Kirin
 * created on 19-11-2024-16h-47m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\SalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Ordering\SalesChannel;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSalesChannel extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Group $group, array $modelData): SalesChannel
    {
        return DB::transaction(function () use ($group, $modelData) {
            /** @var SalesChannel $salesChannel */
            $salesChannel = $group->salesChannels()->create($modelData);
            $salesChannel->stats()->create();

            return $salesChannel;
        });
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
                'required',
                'max:16',
                'alpha_dash',
                new IUnique(
                    table: 'sales_channels',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'name' => [
                'required',
                'string',
                new IUnique(
                    table: 'sales_channels',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'type' => [
                'required',
                Rule::enum(SalesChannelTypeEnum::class),
            ],

        ];

        if (! $this->strict) {
            $rules['is_seeded'] = [
                'required',
                'boolean',
            ];
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): SalesChannel
    {
        if (! $audit) {
            SalesChannel::disableAuditing();
        }

        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
