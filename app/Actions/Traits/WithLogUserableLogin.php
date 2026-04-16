<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 Feb 2024 00:48:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Analytics\WebUserRequest\StoreWebUserLogin;
use App\Actions\SysAdmin\User\StoreUserLogin;
use App\Actions\SysAdmin\WithLogRequest;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;

trait WithLogUserableLogin
{
    use WithLogRequest;

    public function logUserableLogin(string $type, Carbon $datetime, string $ip, string $userAgent, User|WebUser $userable): void
    {
        $browserData = GetBrowserInfo::run($userAgent);

        $body = [
            'type'        => $type,
            'datetime'    => $datetime,
            'username'    => $userable->username,
            'user_id' => $userable->id,
            'ip_address'  => $ip,
            'location'    => $this->getLocation($ip), // reference: https://github.com/stevebauman/location
            'user_agent'  => $userAgent,
            'device_type'          => [
                'title' => $browserData['device'],
                'icon'  => $this->getDeviceIcon($browserData['device'])
            ],
            'platform'             => [
                'title' => $browserData['os'],
                'icon'  => $this->getPlatformIcon($browserData['os'])
            ],
            'browser'              => [
                'title' => $browserData['browser'],
                'icon'  => $this->getBrowserIcon(strtolower($browserData['browser']))
            ]
        ];

        if($userable instanceof WebUser) {
            $body['web_user_id'] = $userable->id;

            StoreWebUserLogin::run($userable, $body);
        } else {
            $body['user_id'] = $userable->id;

            StoreUserLogin::run($userable, $body);
        }
    }
}
