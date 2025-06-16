<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 19:27:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Guest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GuestHydrateTimesheets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Guest $guest): string
    {
        return $guest->id;
    }

    public function handle(Guest $guest): void
    {
        $stats = [
            'number_timesheets' => $guest->timesheets()->count(),
        ];



        $guest->stats()->update($stats);
    }


}
