<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:28 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest;

use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateTimesheets;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateTimeTracker;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\SysAdmin\Guest;

class HydrateGuest
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:guests';

    public function __construct()
    {
        $this->model = Guest::class;
    }

    public function handle(Guest $guest): void
    {
        GuestHydrateTimesheets::run($guest);
        GuestHydrateClockings::run($guest);
        GuestHydrateTimeTracker::run($guest);
    }
}
