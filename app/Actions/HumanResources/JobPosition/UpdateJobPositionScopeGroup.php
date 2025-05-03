<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Aug 2024 11:33:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\HumanResources\JobPosition;
use Illuminate\Validation\Rule;

class UpdateJobPositionScopeGroup extends OrgAction
{
    use WithActionUpdate;

    public function handle(JobPosition $jobPosition, array $modelData): JobPosition
    {
        return $this->update($jobPosition, $modelData, ['data']);
    }



    public function rules(): array
    {
        return [
            'code'       => ['sometimes', 'required', 'max:16'],
            'name'       => ['sometimes', 'required', 'max:255'],
            'scope'      => ['required', Rule::enum(JobPositionScopeEnum::class)],
            'department' => ['sometimes', 'nullable', 'string'],
            'team'       => ['sometimes', 'nullable', 'string']
        ];
    }


    public function action(JobPosition $jobPosition, array $modelData): JobPosition
    {
        $this->asAction = true;
        $this->initialisationFromGroup($jobPosition->group, $modelData);

        return $this->handle($jobPosition, $this->validatedData);
    }


}
