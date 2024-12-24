<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Nov 2024 10:24:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Analytics\UserRequest;

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\GrpAction;
use App\Actions\SysAdmin\User\StoreUserRequest;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UserRequest\WithLocationDetector;
use App\Actions\Traits\UserRequest\WithWindowsDetector;
use App\Models\Analytics\UserRequest;
use App\Models\SysAdmin\User;
use hisorange\BrowserDetect\Parser as Browser;
use Illuminate\Support\Carbon;

class ProcessUserRequest extends GrpAction
{
    use WithNoStrictRules;
    use WithLocationDetector;
    use WithWindowsDetector;

    /**
     * @throws \Throwable
     */
    public function handle(User $user, Carbon $datetime, array $routeData, string $ip, string $userAgent): UserRequest|null
    {
        if ($routeData['name'] == 'grp.search.index') {
            return null;
        }

        $section                = GetSectionRoute::run($routeData['name'], $routeData['arguments']);
        $aiku_scoped_section_id = $section?->id ?? null;


        $parsedUserAgent = (new Browser())->parse($userAgent);
        $modelData       = [
            'date'                   => $datetime,
            'route_name'             => $routeData['name'],
            'route_params'           => json_encode($routeData['arguments']),
            'aiku_scoped_section_id' => $aiku_scoped_section_id,
            'os'                     => $this->detectWindows11($parsedUserAgent),
            'device'                 => $parsedUserAgent->deviceType(),
            'browser'                => explode(' ', $parsedUserAgent->browserName())[0] ?: 'Unknown',
            'ip_address'             => $ip,
            'location'               => json_encode($this->getLocation($ip))
        ];

        return StoreUserRequest::make()->action(
            user: $user,
            modelData: $modelData,
            hydratorsDelay: 300
        );
    }
}
