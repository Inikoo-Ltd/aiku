<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 03 May 2023 08:56:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Auth\GroupUser;

use App\Actions\Auth\User\DeleteUser;
use App\Actions\WithActionUpdate;
use App\Models\Auth\GroupUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteGroupUser
{
    use AsAction;
    use WithActionUpdate;

    private bool $trusted = false;

    public function handle(GroupUser $groupUser): GroupUser
    {
        if (!$groupUser->status) {
            $this->update($groupUser, [
                'username' => $groupUser->username . '@deleted-' . $groupUser->id
            ]);

            foreach ($groupUser->users as $user) {
                DeleteUser::make()->action($user);
            }


            $groupUser->delete();

            return $groupUser;
        }

        return $groupUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->trusted) {
            return true;
        }

        return $request->user()->hasPermissionTo("sysadmin.edit");
    }

    public function asController(GroupUser $groupUser, ActionRequest $request): GroupUser
    {
        return $this->handle($groupUser);
    }

    public function action(GroupUser $groupUser): GroupUser
    {
        return $this->handle($groupUser);
    }
}
