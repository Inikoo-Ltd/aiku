<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Oct 2023 12:34:29 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\CRM\WebUser;
use App\Models\WebUserFailedLogin;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogWebUserFailLogin
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(int $websiteId, array $credentials, string $ip, string $userAgent, Carbon $datetime, array $geoLocation = [], string $source = 'A'): void
    {
        $webUser     = WebUser::withTrashed()->where('username', Arr::get($credentials, 'username'))->where('website_id', $websiteId)->first();
        $browserData = GetBrowserInfo::run($userAgent);

        WebUserFailedLogIn::create(
            [
                'ip_address'  => $ip,
                'website_id'  => $websiteId,
                'username'    => mb_substr(Arr::get($credentials, 'username', ''), 0, 254),
                'web_user_id' => $webUser->id ?? null,
                'failed_at'   => $datetime,
                'os'          => $browserData['os'],
                'device'      => $browserData['device'],
                'browser'     => $browserData['browser'],
                'location'    => json_encode($geoLocation),
                'source'      => $source,
            ]
        );

        if ($webUser) {
            $stats = [
                'number_failed_logins' => $webUser->stats->number_failed_logins + 1,
                'last_failed_login_ip' => $ip,
                'last_failed_login_at' => $datetime
            ];

            $webUser->stats()->update($stats);
        }
    }


}
