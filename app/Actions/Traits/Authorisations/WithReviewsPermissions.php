<?php

namespace App\Actions\Traits\Authorisations;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;

trait WithReviewsPermissions
{
    public function canViewReviews(User $user, Shop $shop): bool
    {
        return $user->authTo([
            "web.{$shop->id}.view",
            "crm.{$shop->id}.view",
            "group-webmaster.view",
        ]);
    }

    public function canManageReviews(User $user, Shop $shop): bool
    {
        return $user->authTo([
            "web.{$shop->id}.edit",
            "supervisor-web.{$shop->id}",
            "crm.{$shop->id}.edit",
            "supervisor-crm.{$shop->id}",
            "group-webmaster.edit",
        ]);
    }
}
