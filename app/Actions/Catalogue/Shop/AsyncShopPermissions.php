<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuScopedSections;
use App\Actions\SysAdmin\User\UserAddRoles;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;

class AsyncShopPermissions extends OrgAction
{
    public function handle(Organisation $organisation, Shop $shop): void
    {
        SeedShopPermissions::run($shop);
        SeedAikuScopedSections::make()->seedShopAikuScopedSection($shop);


        $orgAdmins = $organisation->group->users()->with('roles')->get()->filter(
            fn ($user) => $user->roles->where('name', "org-admin-$organisation->id")->toArray()
        );

        foreach ($orgAdmins as $orgAdmin) {
            UserAddRoles::run($orgAdmin, [
                Role::where('name', RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $shop))->first()
            ]);
        }
    }
}
