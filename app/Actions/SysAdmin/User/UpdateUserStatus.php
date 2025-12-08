<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\UsersResource;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateUserStatus
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(User $user, array $modelData): User
    {

        return $this->update($user, $modelData);
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
        return [
            'status' => ['required', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($request->exists('username') and $request->get('username') != strtolower($request->get('username'))) {
            $validator->errors()->add('invalid_username', 'Username must be lowercase.');
        }
    }

    public function asController(User $user, ActionRequest $request): User
    {
        return $this->handle($user, $request->validated());
    }

    public function action(User $user, bool $status): User
    {
        $this->asAction = true;
        $this->setRawAttributes([
            'status' => $status,
        ]);
        $validatedData = $this->validateAttributes();

        return $this->handle($user, $validatedData);

    }

    public function jsonResponse(User $user): UsersResource
    {
        return new UsersResource($user);
    }
}
