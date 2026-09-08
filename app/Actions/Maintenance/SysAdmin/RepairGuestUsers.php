<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Feb 2025 13:55:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\SysAdmin;

use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\Guest;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairGuestUsers
{
    use WithActionUpdate;



    protected function handle(Guest $guest): Guest
    {

        $numberUsers = $guest->users()->count();
        if ($numberUsers > 1) {
            dd($guest);
        }

        $user = $guest->getUser();
        if ($user) {
            $guest->update(
                [
                    'user_id' => $user->id
                ]
            );
        }

        return $guest;
    }

    public string $commandSignature = 'guests:repair_user_id';

    public function asCommand(): void
    {
        Nightwatch::dontSample();
        $guests = Guest::all();

        foreach ($guests as $guest) {
            $this->handle($guest);
        }
    }

}
