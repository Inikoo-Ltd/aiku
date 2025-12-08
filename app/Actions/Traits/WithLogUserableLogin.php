<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 Feb 2024 00:48:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Elasticsearch\IndexElasticsearchDocument;
use App\Actions\SysAdmin\WithLogRequest;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use hisorange\BrowserDetect\Parser as Browser;
use Illuminate\Support\Carbon;

trait WithLogUserableLogin
{
    use WithLogRequest;

    public function logUserableLogin(string $index, string $type, Carbon $datetime, string $ip, string $userAgent, User|WebUser $userable): void
    {
        $parsedUserAgent = (new Browser)->parse($userAgent);

        $body = [
            'type' => $type,
            'datetime' => $datetime,
            'username' => $userable->username,
            'userable_id' => $userable->id,
            'ip_address' => $ip,
            'location' => json_encode($this->getLocation($ip)), // reference: https://github.com/stevebauman/location
            'user_agent' => $userAgent,
            'device_type' => json_encode([
                'title' => $parsedUserAgent->deviceType(),
                'icon' => $this->getDeviceIcon($parsedUserAgent->deviceType()),
            ]),
            'platform' => json_encode([
                'title' => $this->detectWindows11($parsedUserAgent),
                'icon' => $this->getPlatformIcon($this->detectWindows11($parsedUserAgent)),
            ]),
            'browser' => json_encode([
                'title' => explode(' ', $parsedUserAgent->browserName())[0],
                'icon' => $this->getBrowserIcon(strtolower($parsedUserAgent->browserName())),
            ]),
        ];

        IndexElasticsearchDocument::dispatch(index: $index, body: $body);
    }
}
