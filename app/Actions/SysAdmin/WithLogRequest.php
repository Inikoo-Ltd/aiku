<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

use App\Actions\Elasticsearch\IndexElasticsearchDocument;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Enums\Elasticsearch\ElasticsearchUserRequestTypeEnum;
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


    public function logFail(string $index, Carbon $datetime, string $ip, string $userAgent, string $username, ?int $userID): void
    {
        $index = config('elasticsearch.index_prefix').$index;


        $browserData = GetBrowserInfo::run($userAgent);


        $body = [
            'type'                 => ElasticsearchUserRequestTypeEnum::FAIL_LOGIN->value,
            'datetime'             => $datetime,
            'username'             => $username,
            'organisation_user_id' => $userID,
            'ip_address'           => $ip,
            'location'             => json_encode($this->getLocation($ip)), // reference: https://github.com/stevebauman/location
            'user_agent'           => $userAgent,
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

        IndexElasticsearchDocument::run(index: $index, body: $body);
    }

}
