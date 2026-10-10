<?php

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\User;

trait WithGroupMarketingReaders
{
    /**
     * Marketing staff learn from each other, so anyone doing marketing or offers for one shop may
     * read the newsletters, mailshots, templates and offers of every other shop. Changing them
     * stays with the shop.
     */
    protected function readsMarketingAcrossShops(User $user): bool
    {
        return $user->getAllPermissions()->contains(
            fn ($permission) => preg_match('/^(supervisor-)?(marketing|discounts)\.\d+/', $permission->name) === 1
        );
    }
}
