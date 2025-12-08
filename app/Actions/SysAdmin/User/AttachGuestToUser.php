<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Sept 2024 00:33:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateModels;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;

class AttachGuestToUser extends GrpAction
{
    public function handle(User $user, Guest $guest, array $modelData): User
    {
        data_set($modelData, 'group_id', $guest->group_id);

        $user->guests()->syncWithoutDetaching([
            $guest->id => $modelData,
        ]);
        SyncRolesFromJobPositions::run($user);
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

    public function action(User $user, Guest $guest, array $modelData): User
    {
        $this->initialisation($guest->group, $modelData);

        return $this->handle($user, $guest, $modelData);
    }
}
