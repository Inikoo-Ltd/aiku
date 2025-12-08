<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 21:07:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\JobPositionCategory;

use App\Actions\GrpAction;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\JobPositionCategory;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;

class StoreJobPositionCategory extends GrpAction
{
    public function handle(Group $group, array $modelData): JobPositionCategory
    {
        /** @var JobPositionCategory $jobPositionCategory */
        $jobPositionCategory = $group->jobPositionCategories()->create($modelData);

        return $jobPositionCategory;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                new IUnique(
                    table: 'job_position_categories',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ],
                ),
                'max:16',
                'alpha_dash',
            ],
            'name' => ['required', 'max:255'],
            'scope' => ['required', Rule::enum(JobPositionScopeEnum::class)],
            'department' => ['sometimes', 'nullable', 'string'],
            'team' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function action(Group $group, array $modelData): JobPositionCategory
    {
        $this->asAction = true;

        $this->initialisation($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }
}
