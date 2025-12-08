<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Oct 2023 12:34:29 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\SysAdmin\WithLogRequest;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogUserFailLogin
{
    use AsAction;
    use WithLogRequest;

    public function handle(array $credentials, string $ip, string $userAgent, Carbon $datetime): void
    {
        $user = User::withTrashed()->where('username', Arr::get($credentials, 'username'))->first();

        $this->logFail('users_requests', $datetime, $ip, $userAgent, Arr::get($credentials, 'username'), $user?->id);

        if ($user) {
            $stats = [
                'number_failed_logins' => $user->stats->number_failed_logins + 1,
                'last_failed_login_ip' => $ip,
                'last_failed_login_at' => $datetime,
            ];

            $user->stats()->update($stats);
        }
    }
}
