<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Sept 2024 00:01:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateModels;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;

class AttachEmployeeToUser extends OrgAction
{
    public function handle(User $user, Employee $employee, array $modelData): User
    {
        data_set($modelData, 'group_id', $employee->group_id);
        data_set($modelData, 'organisation_id', $employee->organisation_id);

        $user->employees()->syncWithoutDetaching([
            $employee->id => $modelData,
        ]);
        SetUserAuthorisedModels::run($user);
        UserHydrateModels::dispatch($user);

        return $user;
    }

    public function rules(): array
    {
        return [
            'source_id' => ['sometimes', 'nullable', 'string'],
            'status' => ['required', 'bool'],
        ];
    }

    public function action(User $user, Employee $employee, array $modelData): User
    {
        $this->initialisation($employee->organisation, $modelData);

        return $this->handle($user, $employee, $modelData);
    }
}
