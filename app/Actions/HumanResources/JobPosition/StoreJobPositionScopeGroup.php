<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Aug 2024 11:33:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateJobPositions;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;

class StoreJobPositionScopeGroup extends OrgAction
{
    public function handle(Group $group, array $modelData): JobPosition
    {
        /** @var JobPosition $jobPosition */
        $jobPosition = $group->jobPositions()->create($modelData);
        $jobPosition->stats()->create();

        GroupHydrateJobPositions::run($group);

        return $jobPosition;
    }



    public function rules(): array
    {
        return [
            'code'                  => [
                'required',
                new IUnique(
                    table: 'job_positions',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id]
                    ],
                ),
                'max:16',
                'alpha_dash'
            ],
            'name'                  => ['required', 'max:255'],
            'locked'                => ['sometimes', 'boolean'],
            'scope'                 => ['required', Rule::enum(JobPositionScopeEnum::class)],
            'department'            => ['sometimes', 'nullable', 'string'],
            'team'                  => ['sometimes', 'nullable', 'string'],
            'group_job_position_id' => ['sometimes', 'nullable', 'exists:job_position_categories,id'],
        ];
    }



    public function action(Group $group, array $modelData): JobPosition
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }


}
