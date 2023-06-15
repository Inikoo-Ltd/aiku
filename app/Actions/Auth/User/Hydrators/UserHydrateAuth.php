<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Auth\User\Hydrators;

use App\Actions\WithTenantJob;
use App\Models\Auth\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UserHydrateAuth implements ShouldBeUnique
{
    use AsAction;
    use WithTenantJob;

    public function handle(User $user): void
    {
        $stats = [];
        $numberLogins = $user->stats->number_logins;

<<<<<<< Updated upstream
        if(auth()->check()) {
=======
        if(! auth()->check()) {
>>>>>>> Stashed changes
            $stats = [
                'login_at' => now(),
                'last_login' => now(),
                'number_logins' => $numberLogins + 1
            ];
        }

        if(! auth()->check()) {
            $stats = [
                'login_at' => now(),
<<<<<<< Updated upstream
//                'failed_login' => request()->ip(),
=======
                'failed_login' => request()->ip(),
>>>>>>> Stashed changes
                'failed_login_at' => now()
            ];
        }

        $user->stats()->update($stats);
    }

    public function getJobUniqueId(User $user): string
    {
        return $user->id;
    }
}
