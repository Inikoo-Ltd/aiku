<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\SysAdmin;

use App\Actions\RetinaAction;
use App\Actions\SysAdmin\WithLogRequest;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Utils\GetOsFromUserAgent;
use App\Models\Analytics\WebUserRequest;
use App\Models\CRM\WebUser;
use hisorange\BrowserDetect\Parser as Browser;
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


        $parsedUserAgent = (new Browser())->parse($userAgent);
        $modelData       = [
            'date'                   => $datetime,
            'route_name'             => $routeData['name'],
            'route_params'           => json_encode($routeData['arguments']),
            'os'                     => GetOsFromUserAgent::run($parsedUserAgent),
            'device'                 => $parsedUserAgent->deviceType(),
            'browser'                => explode(' ', $parsedUserAgent->browserName())[0] ?: 'Unknown',
            'ip_address'             => $ip,
            'location'               => json_encode($this->getLocation($ip)),
        ];

        return StoreWebUserRequest::make()->action(
            webUser: $webUser,
            modelData: $modelData,
            hydratorsDelay: 300
        );
    }
}
