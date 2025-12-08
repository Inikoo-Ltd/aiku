<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUsers;
use App\Actions\SysAdmin\User\Search\UserRecordSearch;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreUser extends GrpAction
{
    private Employee|Guest $parent;

    /**
     * @throws \Throwable
     */
    public function handle(Guest|Employee $parent, array $modelData = []): User
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'contact_name', $parent->contact_name);

        $userModelStatus = $this->get('user_model_status', Arr::get($modelData, 'status', false));
        data_forget($modelData, 'user_model_status');

        $user = DB::transaction(function () use ($parent, $modelData, $userModelStatus) {
            /** @var User $user */
            $user = User::create($modelData);
            $user->stats()->create();

            if ($parent instanceof Guest) {
                $parent->update(
                    [
                        'user_id' => $user->id,
                    ]
                );
            }

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $user->timeSeries()->create(['frequency' => $frequency]);
            }
            $user->refresh();
            if ($parent instanceof Employee) {
                $user = AttachEmployeeToUser::make()->action($user, $parent, [
                    'status' => $userModelStatus,
                    'source_id' => $user->source_id,
                ]);
            } else {
                $user = AttachGuestToUser::make()->action($user, $parent, [
                    'status' => $userModelStatus,
                    'source_id' => $user->source_id,
                ]);
            }

            if ($this->hydratorsDelay) {
                SetIconAsUserImage::dispatch($user)->delay($this->hydratorsDelay);
            } else {
                SetIconAsUserImage::run($user);
            }

            SyncRolesFromJobPositions::run($user);

            return $user;
        });

        UserRecordSearch::dispatch($user);
        GroupHydrateUsers::dispatch($user->group)->delay($this->hydratorsDelay);

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
            'username' => [
                'required',
                $this->strict ? new AlphaDashDot : 'string',
                new IUnique(
                    table: 'users',
                    column: 'username',
                ),
                Rule::notIn(['export', 'create']),
            ],
            'password' => ['required', app()->isLocal() || app()->environment('testing') || ! $this->strict ? Password::min(3) : Password::min(8)],
            'reset_password' => ['sometimes', 'boolean'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                new IUnique(
                    table: 'users'
                ),
            ],
            'contact_name' => ['sometimes', 'string', 'max:255'],
            'auth_type' => ['sometimes', Rule::enum(UserAuthTypeEnum::class)],
            'status' => ['required', 'boolean'],
            'user_model_status' => ['sometimes', 'boolean'],
            'language_id' => ['sometimes', 'required', 'exists:languages,id'],
        ];

        if (! $this->strict) {
            $rules['deleted_at'] = ['sometimes', 'date'];
            $rules['created_at'] = ['sometimes', 'date'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id'] = ['sometimes', 'string', 'max:255'];
            $rules['legacy_password'] = ['sometimes', 'string'];
        }

        return $rules;
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->get('status') and $this->parent->user) {
            $validator->errors()->add('user', __('This record already has a user associated with it.'));
        }

        if ($this->get('username') != strtolower($this->get('username'))) {
            $validator->errors()->add('user', __('Username must be lowercase.'));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Guest|Employee $parent, array $modelData = [], int $hydratorsDelay = 0, bool $strict = true, $audit = true): User
    {
        if (! $audit) {
            User::disableAuditing();
        }

        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent = $parent;

        $this->initialisation($parent->group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }
}
