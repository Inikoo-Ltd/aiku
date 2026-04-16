<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Oct 2023 12:34:29 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\SysAdmin\User;
use App\Models\UserFailedLogIn;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogUserFailLogin
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(array $credentials, string $ip, string $userAgent, Carbon $datetime, array $geoLocation = []): void
    {
        $user = User::withTrashed()->where('username', Arr::get($credentials, 'username'))->first();

        $browserData = GetBrowserInfo::run($userAgent);

        UserFailedLogIn::create(
            [
                'ip_address' => $ip,
                'username'   => mb_substr(Arr::get($credentials, 'username', ''), 0, 254),
                'user_id'    => $user->id ?? null,
                'failed_at'  => $datetime,
                'os'         => $browserData['os'],
                'device'     => $browserData['device'],
                'browser'    => $browserData['browser'],
                'location'   => json_encode($geoLocation),
            ]
        );

        if ($user) {
            $stats = [
                'number_failed_logins' => $user->stats->number_failed_logins + 1,
                'last_failed_login_ip' => $ip,
                'last_failed_login_at' => $datetime
            ];

            $user->stats()->update($stats);
        }
    }


}
