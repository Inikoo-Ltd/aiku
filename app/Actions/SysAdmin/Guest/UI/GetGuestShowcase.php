<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Guest\UI;

use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Actions\SysAdmin\User\GetUserOrganisationScopeJobPositionsData;
use App\Actions\Traits\UI\WithPermissionsPictogram;
use App\Actions\Utils\GetLocationFromIp;
use App\Models\SysAdmin\Guest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetGuestShowcase
{
    use AsObject;
    use WithPermissionsPictogram;


    public function handle(Guest $guest): array
    {
        $user = $guest->getUser();

        $jobPositionsOrganisationsData = [];
        foreach ($guest->group->organisations as $organisation) {
            $jobPositionsOrganisationData                       = GetUserOrganisationScopeJobPositionsData::run($user, $organisation);
            $jobPositionsOrganisationsData[$organisation->slug] = $jobPositionsOrganisationData;
        }


        $permissionsGroupData = GetUserGroupScopeJobPositionsData::run($user);

        return [
            'data' => [
                'id'                    => $guest->id,
                'username'              => $user->username,
                'email'                 => $guest->email,
                'about'                 => $user->about,
                'contact_name'          => $guest->contact_name,
                'permissions_pictogram' => $this->getPermissionsPictogram($user, $permissionsGroupData, $jobPositionsOrganisationsData),
                'last_active_at'        => $user->stats->last_active_at,
                'last_login'            => [
                    'ip'          => $user->stats->last_login_ip,
                    'geolocation' => GetLocationFromIp::run($user->stats->last_login_ip)
                ]
            ]
        ];
    }
}
