<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Actions\Analytics\WebUserRequest\StoreWebUserFailedLogin;
use App\Actions\Elasticsearch\IndexElasticsearchDocument;
use App\Actions\SysAdmin\User\StoreUserFailedLogin;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Enums\Elasticsearch\ElasticsearchUserRequestTypeEnum;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Stevebauman\Location\Facades\Location;

trait WithLogRequest
{
    public function getDeviceIcon($deviceType): string
    {
        if ($deviceType == 'Desktop') {
            return 'far fa-desktop-alt';
        } elseif ($deviceType == 'Bot') {
            return 'fas fa-robot';
        }

        return 'fas fa-mobile-alt';
    }

    public function getBrowserIcon($browser): string
    {
        if (explode(' ', $browser)[0] == 'chrome') {
            return 'fab fa-chrome';
        } elseif ($browser == 'microsoft') {
            return 'fab fa-edge';
        }

        return 'fab fa-firefox-browser';
    }

    public function getPlatformIcon($platform): string
    {
        if ($platform == 'android') {
            return 'fab fa-android';
        } elseif ($platform == 'apple') {
            return 'fab fa-apple';
        }

        return 'fab fa-windows';
    }

    public function getLocation(string $ip): false|array|null
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            return ['localhost'];
        }

        if ($position = Location::get($ip)) {
            return [
                $position->countryCode,
                $position->countryName,
                $position->cityName
            ];
        }

        return false;
    }


    public function logFail(Carbon $datetime, string $ip, string $userAgent, string $username, WebUser|User|null $userable): void
    {
        $browserData = GetBrowserInfo::run($userAgent);

        $body = [
            'type'                 => ElasticsearchUserRequestTypeEnum::FAIL_LOGIN->value,
            'datetime'             => $datetime,
            'username'             => $username,
            'ip_address'           => $ip,
            'location'             => $this->getLocation($ip), // reference: https://github.com/stevebauman/location
            'user_agent'           => $userAgent,
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

            StoreWebUserFailedLogin::run($userable, $body);
        } else {
            $body['user_id'] = $userable->id;

            StoreUserFailedLogin::run($userable, $body);
        }
    }

}
