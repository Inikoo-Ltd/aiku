<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Feb 2024 10:40:25 CST, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\CRM\WebUser;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogWebUserLogin
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(WebUser $webUser, string $ip, string $userAgent, Carbon $datetime, array $geoLocation = [], $source = 'A'): void
    {
        $browserData = GetBrowserInfo::run($userAgent);

        $webUser->webUserLogins()->create(
            [
                'ip_address' => $ip,
                'date'       => $datetime,
                'os'         => $browserData['os'],
                'device'     => $browserData['device'],
                'browser'    => $browserData['browser'],
                'location'   => json_encode($geoLocation),
                'source'     => $source,
            ]
        );

        $stats = [
            'last_login_at' => $datetime,
            'last_login_ip' => $ip,
            'number_logins' => ($webUser->stats->number_logins ?? 0) + 1
        ];

        $webUser->stats()->update($stats);
    }


}
