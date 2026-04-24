<?php

/*
 * Author: Arya Permana - Kirin <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 16:30
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUserRequests;
use App\Models\Analytics\UserRequest;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class StoreUserRequest extends GrpAction
{
    public function handle(User $user, array $modelData): UserRequest
    {
        data_set($modelData, 'group_id', $user->group_id);
        /** @var UserRequest $userRequest */
        $userRequest = $user->userRequests()->create($modelData);
        GroupHydrateUserRequests::dispatch($user->group)->delay(now()->addMinutes(10));

        return $userRequest;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'date'                   => ['required', 'date'],
            'os'                     => ['required', 'string'],
            'route_name'             => ['required', 'string'],
            'route_params'           => ['required'],
            'aiku_scoped_section_id' => ['nullable', 'integer'],
            'device'                 => ['required', 'string'],
            'browser'                => ['required', 'string'],
            'ip_address'             => ['required', 'string'],
            'location'               => ['required']
        ];
    }

    public function action(User $user, array $modelData, int $hydratorsDelay = 0): UserRequest
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($user->group, $modelData);

        return $this->handle($user, $this->validatedData);
    }
}
