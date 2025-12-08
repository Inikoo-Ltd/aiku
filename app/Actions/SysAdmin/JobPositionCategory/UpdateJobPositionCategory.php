<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Jun 2023 11:39:33 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\JobPositionCategory;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\SysAdmin\JobPositionCategory;
use Illuminate\Validation\Rule;

class UpdateJobPositionCategory extends GrpAction
{
    use WithActionUpdate;

    public function handle(JobPositionCategory $jobPositionCategory, array $modelData): JobPositionCategory
    {
        return $this->update($jobPositionCategory, $modelData, ['data']);
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'max:16'],
            'name' => ['sometimes', 'required', 'max:255'],
            'scope' => ['required', Rule::enum(JobPositionScopeEnum::class)],
            'department' => ['sometimes', 'nullable', 'string'],
            'team' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function action(JobPositionCategory $jobPositionCategory, array $modelData): JobPositionCategory
    {
        $this->initialisation($jobPositionCategory->group, $modelData);

        return $this->handle($jobPositionCategory, $this->validatedData);
    }
}
