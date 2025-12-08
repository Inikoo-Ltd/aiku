<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\SysAdmin\User\Traits\WithRolesCommand;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UserAddRoles
{
    use AsAction;
    use WithActionUpdate;
    use WithRolesCommand;

    private bool $trusted = false;

    public function handle(User $user, array $roles, bool $setUserAuthorisedModels = true): User
    {

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        if ($setUserAuthorisedModels) {
            SetUserAuthorisedModels::run($user);
        }

        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->trusted) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    public function rules(): array
    {
        return [
            'role_names' => ['required', 'array'],

        ];
    }

    public function afterValidator(Validator $validator): void
    {

        $roles = [];
        foreach ($this->get('role_names') as $roleName) {
            /** @var Role $role */
            if ($role = Role::where('name', $roleName)->first()) {
                $roles[] = $role;
            } else {
                $validator->errors()->add('roles', "Role $roleName not found");
            }
        }

        $this->set('roles', $roles);

    }

    public function asController(User $user, ActionRequest $request): User
    {
        return $this->handle($user, $request->validated());
    }

    public function action(User $user, array $roleNames): User
    {
        $this->trusted = true;
        $this->setRawAttributes(
            [
                'role_names' => $roleNames,
            ]
        );
        $this->validateAttributes();

        return $this->handle($user, $this->get('roles'));
    }
}
