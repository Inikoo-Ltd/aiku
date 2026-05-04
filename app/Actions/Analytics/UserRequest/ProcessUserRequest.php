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
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\Analytics\UserRequest;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;

class ProcessUserRequest extends GrpAction
{
    public string $jobQueue = 'analytics';

    /**
     * @throws \Throwable
     */
    public function handle(User $user, Carbon $datetime, array $routeData, string $ip, string $userAgent, array $geoLocation = []): UserRequest|null
    {
        if ($routeData['name'] == 'grp.search.index') {
            return null;
        }

        $section                = GetSectionRoute::run($routeData['name'], $routeData['arguments']);
        $aiku_scoped_section_id = $section?->id ?? null;


        $browserData = GetBrowserInfo::run($userAgent);

        $modelData = [
            'date'                   => $datetime,
            'route_name'             => $routeData['name'],
            'route_params'           => json_encode($routeData['arguments']),
            'aiku_scoped_section_id' => $aiku_scoped_section_id,
            'os'                     => $browserData['os'],
            'device'                 => $browserData['device'],
            'browser'                => $browserData['browser'],
            'ip_address'             => $ip,
            'location'               => json_encode($geoLocation),
        ];


        $user->stats()->update([
            'last_active_at' => $datetime
        ]);

        return StoreUserRequest::make()->action(
            user: $user,
            modelData: $modelData,
            hydratorsDelay: 300
        );
    }
}
