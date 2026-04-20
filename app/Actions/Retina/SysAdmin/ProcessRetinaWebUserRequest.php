<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created: Mon, 20 Jan 2025 02:08:09 Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\Analytics\WebUserRequest\StoreWebUserRequest;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\Analytics\WebUserRequest;
use App\Models\CRM\WebUser;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessRetinaWebUserRequest
{
    use AsAction;

    public string $jobQueue = 'analytics';

    /**
     * @throws \Throwable
     */
    public function handle(?WebUser $webUser, Carbon $datetime, array $routeData, string $ip, string $userAgent, array $geoLocation): WebUserRequest|null
    {
        if (!$webUser) {
            return null;
        }

        if ($routeData['name'] == 'retina.search.index') {
            return null;
        }
        $webpageID = null;
        if (str_starts_with($routeData['name'], 'iris.iris_webpage')) {
            $website = $webUser->website;
            $path    = end($routeData['arguments']);
            if (config('iris.cache.webpage_path.ttl') == 0) {
                $webpageID = ShowIrisWebpage::make()->getWebpageID($website, $path);
            } else {
                $key       = config('iris.cache.webpage_path.prefix').'_'.$website->id.'_'.$path;
                $webpageID = cache()->remember($key, config('iris.cache.webpage_path.ttl'), function () use ($website, $path) {
                    return ShowIrisWebpage::make()->getWebpageID($website, $path);
                });
            }
        }


        $browserData = GetBrowserInfo::run($userAgent);

        $modelData = [
            'date'         => $datetime,
            'route_name'   => $routeData['name'],
            'route_params' => json_encode($routeData['arguments']),
            'os'           => $browserData['os'],
            'device'       => $browserData['device'],
            'browser'      => $browserData['browser'],
            'ip_address'   => $ip,
            'location'     => json_encode($geoLocation),
            'webpage_id'   => $webpageID,
        ];


        $webUserRequest = StoreWebUserRequest::run(
            webUser: $webUser,
            modelData: $modelData,
        );

        $webUser->stats()->update([
            'last_device'    => $browserData['device'],
            'last_os'        => $browserData['os'],
            'last_location'  => json_encode($geoLocation),
            'last_active_at' => $datetime
        ]);


        return $webUserRequest;
    }
}
