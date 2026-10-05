<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\User;

trait WithGroupDashboardSalesAuthorisation
{
    public function canViewGroupDashboardSales(User $user): bool
    {
        return $user->canViewSales();
    }
}
