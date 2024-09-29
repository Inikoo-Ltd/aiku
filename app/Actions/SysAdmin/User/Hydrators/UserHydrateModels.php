<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Sept 2024 12:28:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Hydrators;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UserHydrateModels
{
    use AsAction;

    public function handle(User $user): void
    {
        $stats = [
            'number_employees' => $user->employees()->count(),
            'number_active_employees' => $user->employees()->wherePivot('status', true)->count(),
            'number_guests' => $user->guests()->count(),
            'number_active_guests' => $user->guests()->wherePivot('status', true)->count(),
        ];

        $stats['number_models'] = $stats['number_employees'] + $stats['number_guests'];
        $stats['number_active_models'] = $stats['number_active_employees'] + $stats['number_active_guests'];

        $user->updateQuietly($stats);
    }


}
