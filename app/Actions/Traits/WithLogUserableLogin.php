<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 17 Feb 2024 00:48:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Elasticsearch\IndexElasticsearchDocument;
use App\Actions\SysAdmin\WithLogRequest;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;

trait WithLogUserableLogin
{
    use WithLogRequest;

    public function logUserableLogin(string $index, string $type, Carbon $datetime, string $ip, string $userAgent, User|WebUser $userable): void
    {
        $browserData = GetBrowserInfo::run($userAgent);

        $body = [
            'type'        => $type,
            'datetime'    => $datetime,
            'username'    => $userable->username,
            'userable_id' => $userable->id,
            'ip_address'  => $ip,
            'location'    => json_encode($this->getLocation($ip)), // reference: https://github.com/stevebauman/location
            'user_agent'  => $userAgent,
            'device_type'          => json_encode([
                'title' => $browserData['device'],
                'icon'  => $this->getDeviceIcon($browserData['device'])
            ]),
            'platform'             => json_encode([
                'title' => $browserData['os'],
                'icon'  => $this->getPlatformIcon($browserData['os'])
            ]),
            'browser'              => json_encode([
                'title' => $browserData['browser'],
                'icon'  => $this->getBrowserIcon(strtolower($browserData['browser']))
            ])
        ];

        IndexElasticsearchDocument::dispatch(index: $index, body: $body);
    }
}
