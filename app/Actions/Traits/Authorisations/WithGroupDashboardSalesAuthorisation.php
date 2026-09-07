<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

trait WithGroupDashboardSalesAuthorisation
{
    public function canViewGroupDashboardSales(User $user): bool
    {
        if ($user->authTo('group-overview')) {
            return true;
        }

        return $user->authorisedShopOrganisations->contains(
            fn (Organisation $organisation) => $user->authTo([
                'accounting.'.$organisation->id.'.view',
                'org-supervisor.'.$organisation->id,
                'shops-view.'.$organisation->id,
            ])
        );
    }
}
