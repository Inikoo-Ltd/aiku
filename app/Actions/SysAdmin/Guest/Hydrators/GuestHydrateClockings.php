<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Guest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GuestHydrateClockings implements ShouldBeUnique
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
            'number_clockings' => $guest->clockings()->count(),
        ];

        $guest->stats()->update($stats);
    }


}
