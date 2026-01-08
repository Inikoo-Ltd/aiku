<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShops;
use App\Actions\SysAdmin\Group\Seeders\SeedAikuScopedSections;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDepartments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamilies;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShops;
use App\Actions\SysAdmin\User\UserAddRoles;
use App\Actions\Web\Website\DeleteWebsite;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use Illuminate\Console\Command;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class AsyncShopPermissions extends OrgAction
{
    public $jobQueue = 'urgent';

    public function handle(Shop $shop): void
    {

        $organisation=$shop->organisation;
        setPermissionsTeamId($shop->group->id);

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

    public function getCommandSignature(): string
    {
        return 'shop:hydrate_permissions {shop}';
    }

    public function getCommandDescription(): string
    {
        return 'Hydrate permissions for a shop';
    }

    public function asCommand(Command$command): int
    {
      $shop = Shop::where('slug', $command->argument('shop'))->first();
      $this->handle($shop);
      return 0;
    }

}
