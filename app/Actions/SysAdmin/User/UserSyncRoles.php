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

class UserSyncRoles
{
    use AsAction;
    use WithActionUpdate;
    use WithRolesCommand;

    private bool $trusted = false;

    public function handle(User $user, array $roles): User
    {

        $user->syncRoles($roles);
        SetUserAuthorisedModels::run($user);

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
            'role_names' => ['present', 'array', 'min:0'],

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

    public function action(User $user, array $role_names): User
    {
        $this->trusted = true;
        $this->setRawAttributes(
            [
                'role_names' => $role_names,
            ]
        );
        $this->validateAttributes();

        return $this->handle($user, $this->get('roles'));
    }

    public string $commandSignature = 'user:sync-roles {user : User slug} {roles* : list of roles}';
}
