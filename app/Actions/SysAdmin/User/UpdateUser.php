<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUsers;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Http\Resources\SysAdmin\UsersResource;
use App\Models\SysAdmin\User;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Lorisleiva\Actions\ActionRequest;

class UpdateUser extends OrgAction
{
    use WithActionUpdate;


    private User $user;

    public function handle(User $user, array $modelData): User
    {
        if (Arr::exists($modelData, 'password')) {
            data_set($modelData, 'auth_type', UserAuthTypeEnum::DEFAULT);
        }

        $user = $this->update($user, $modelData, ['profile', 'settings']);

        if ($user->wasChanged('status')) {
            GroupHydrateUsers::dispatch($user->group)->delay($this->hydratorsDelay);
            if (!$user->status) {
                $user->tokens()->each(function ($token) {
                    DeleteUserAccessToken::run($token);
                });
            }

        }

        return $user;
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
            'username'       => [
                'sometimes',
                'required',
                'lowercase',

                $this->strict ? new AlphaDashDot() : 'string',

                Rule::notIn(['export', 'create']),
                new IUnique(
                    table: 'users',
                    extraConditions: [
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->user->id
                        ],
                    ]
                ),
            ],
            'password'       => ['sometimes', 'required', app()->isLocal() || app()->environment('testing') || !$this->strict ? Password::min(3) : Password::min(8)],
            'email'          => [
                'sometimes',
                'nullable',
                'email',
                new IUnique(
                    table: 'employees',
                    extraConditions: [
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->user->id
                        ],
                    ]
                ),
            ],
            'contact_name'   => ['sometimes', 'string', 'max:255'],
            'settings'       => ['sometimes'],
            'reset_password' => ['sometimes', 'boolean'],
            'auth_type'      => ['sometimes', Rule::enum(UserAuthTypeEnum::class)],
            'status'         => ['sometimes', 'boolean'],
            'language_id'    => ['sometimes', 'required', 'exists:languages,id'],
        ];

        if (!$this->strict) {
            $rules['deleted_at']      = ['sometimes', 'date'];
            $rules['created_at']      = ['sometimes', 'date'];
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']       = ['sometimes', 'string', 'max:255'];
            $rules['legacy_password'] = ['sometimes', 'string'];
        }

        return $rules;
    }

    public function asController(User $user, ActionRequest $request): User
    {
        $this->user = $user;
        $this->initialisationFromGroup($user->group, $request);

        return $this->handle($user, $this->validatedData);
    }

    public function action(User $user, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): User
    {
        $this->strict = $strict;
        if (!$audit) {
            User::disableAuditing();
        }

        $this->user           = $user;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($user->group, $modelData);

        return $this->handle($user, $this->validatedData);
    }

    public function jsonResponse(User $user): UsersResource
    {
        return new UsersResource($user);
    }


}
