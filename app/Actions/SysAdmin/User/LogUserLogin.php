<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Oct 2023 12:26:29 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogUserLogin
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(User $user, string $ip, string $userAgent, Carbon $datetime, array $geoLocation = []): void
    {
        $browserData = GetBrowserInfo::run($userAgent);

        $user->userLogins()->create(
            [
                'ip_address' => $ip,
                'date'       => $datetime,
                'os'         => $browserData['os'],
                'device'     => $browserData['device'],
                'browser'    => $browserData['browser'],
                'location'   => json_encode($geoLocation),
            ]
        );


        $stats = [
            'last_login_at' => $datetime,
            'last_login_ip' => $ip,
            'number_logins' => $user->stats->number_logins + 1
        ];

        $user->stats()->update($stats);
    }


}
