<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Dec 2023 22:08:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Actions\SysAdmin\User\UI\GetUserOrganisationLayout;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLayout
{
    use AsAction;

    public function handle(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'group'          => $this->getGroupData($user->group),
            'organisations'  => GetUserOrganisationLayout::make()->getOrganisations($user),
            'agents'         => GetUserOrganisationLayout::make()->getAgents($user),
            'digital_agency' => GetUserOrganisationLayout::make()->getDigitalAgencies($user),
            'bookmarks'      => $user->bookmarks,
            'navigation'     => [
                'grp' => GetGroupNavigation::run($user),
                'org' => GetOrganisationsLayout::run($user),
            ],
            'app_theme'      => $user->settings['app_theme'] ?? null,


        ];
    }

    public function getGroupData(Group $group): array
    {
        $currency = $group->currency;
        return [
            'id'       => $group->id,
            'slug'     => $group->slug,
            'label'    => $group->name,
            'logo'     => $group->imageSources(48, 48),
            'currency' => [
                'id'     => $currency->id,
                'code'   => $currency->code,
                'name'   => $currency->name,
                'symbol' => $currency->symbol
            ]
        ];
    }
}
