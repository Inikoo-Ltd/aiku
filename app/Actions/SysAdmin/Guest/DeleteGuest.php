<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateGuests;
use App\Actions\SysAdmin\User\DeleteUser;
use App\Models\SysAdmin\Guest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteGuest
{
    use AsAction;
    use WithAttributes;

    private bool $isAction = false;

    public function handle(Guest $guest): Guest
    {
        $guest->delete();

        foreach ($guest->users as $user) {
            DeleteUser::run($user);
        }
        GroupHydrateGuests::dispatch($guest->group);

        return $guest;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->isAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    public function asController(Guest $guest, ActionRequest $request): Guest
    {
        $request->validate();

        return $this->handle($guest);
    }

    public function action(Guest $guest): Guest
    {
        $this->isAction = true;

        return $this->handle($guest);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.sysadmin.guests.index');
    }
}
