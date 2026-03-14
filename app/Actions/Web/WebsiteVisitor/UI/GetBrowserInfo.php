<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 11:42:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebsiteVisitor\UI;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\OperatingSystem;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBrowserInfo
{
    use AsAction;

    public function handle(string $userAgent): array
    {
        $cacheKey = 'browser_info_v1:'.md5($userAgent.serialize(request()->server()));

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $clientHints = ClientHints::factory(request()->server());

        try {
            $dd = new DeviceDetector($userAgent, $clientHints);
        } catch (Exception) {
            return [
                'device'  => 'Unknown Device',
                'browser' => 'Unknown',
                'os'      => 'Unknown'
            ];
        }

        $dd->setCache(
            new \DeviceDetector\Cache\LaravelCache()
        );

        $dd->parse();

        if ($dd->isBot()) {
            $botInfo = $dd->getBot();

            $browserData = [
                'device'  => 'Bot',
                'browser' => Arr::get($botInfo, 'name'),
                'os'      => Arr::get($botInfo, 'category'),
            ];
        } else {
            $browserData = [
                'device'  => ucfirst($dd->getDeviceName()) ?? 'Unknown Device',
                'browser' => Browser::getBrowserFamily($dd->getClient('name')),
                'os'      => OperatingSystem::getOsFamily($dd->getOs('name')),
            ];
        }

        if (!$browserData['browser']) {
            $browserData['browser'] = 'Unknown';
        }
        if ($browserData['device'] == '') {
            $browserData['device'] = 'Unknown Device';
        }
        if (!$browserData['os']) {
            $browserData['os'] = 'Unknown';
        }

        Cache::put($cacheKey, $browserData, now()->days(100));


        return $browserData;
    }

}
