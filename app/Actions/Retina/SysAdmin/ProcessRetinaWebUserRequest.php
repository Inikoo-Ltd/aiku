<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\Analytics\WebUserRequest\StoreWebUserRequest;
use App\Actions\RetinaAction;
use App\Actions\SysAdmin\WithLogRequest;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\Analytics\WebUserRequest;
use App\Models\CRM\WebUser;
use Illuminate\Support\Carbon;

class ProcessRetinaWebUserRequest extends RetinaAction
{
    use WithNoStrictRules;
    use WithLogRequest;

    public string $jobQueue = 'analytics';

    /**
     * @throws \Throwable
     */
    public function handle(WebUser $webUser, Carbon $datetime, array $routeData, string $ip, string $userAgent): WebUserRequest|null
    {
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

        $location = json_encode($this->getLocation($ip));

        $browserData = GetBrowserInfo::run($userAgent);

        $modelData = [
            'date'         => $datetime,
            'route_name'   => $routeData['name'],
            'route_params' => json_encode($routeData['arguments']),
            'os'           => $browserData['os'],
            'device'       => $browserData['device'],
            'browser'      => $browserData['browser'],
            'ip_address'   => $ip,
            'location'     => $location,
            'webpage_id'   => $webpageID,
        ];


        $webUserRequest = StoreWebUserRequest::run(
            webUser: $webUser,
            modelData: $modelData,
        );

        $webUser->stats()->update([
            'last_device'    => $device,
            'last_os'        => $os,
            'last_location'  => $location,
            'last_active_at' => $datetime
        ]);


        return $webUserRequest;
    }
}
