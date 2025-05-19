<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Dec 2024 21:40:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetUserAuthorisedModels
{
    use AsAction;

    public function handle(User $user): void
    {
        setPermissionsTeamId($user->group->id);

        $authorisedOrganisations = [];
        $authorisedShops         = [];
        $authorisedFulfilments   = [];
        $authorisedWarehouses    = [];
        $authorisedProductions   = [];


        if ($user->authTo(['group-webmaster.view', 'group-webmaster.edit', 'group-webmaster.view'])) {
            foreach ($user->group->websites as $website) {
                $authorisedOrganisations[$website->organisation_id] = ['org_id' => $website->organisation_id];
                $shop                                               = $website->shop;
                if ($shop->type === ShopTypeEnum::FULFILMENT) {
                    $authorisedFulfilments[$shop->fulfilment->id] = ['org_id' => $shop->organisation_id];
                } else {
                    $authorisedShops[$shop->id] = ['org_id' => $shop->organisation_id];
                }
            }
        }


        foreach ($user->getAllPermissions() as $permission) {
            if ($permission->scope_type === 'Organisation') {
                $authorisedOrganisations[$permission->scope_id] = ['org_id' => $permission->scope_id];
            } elseif ($permission->scope_type === 'Shop') {
                /** @var Shop $shop */
                $shop                                            = Shop::find($permission->scope_id);
                $authorisedShops[$permission->scope_id]          = ['org_id' => $shop->organisation_id];
                $authorisedOrganisations[$shop->organisation_id] = ['org_id' => $shop->organisation_id];
            } elseif ($permission->scope_type === 'Fulfilment') {
                /** @var Fulfilment $fulfilment */
                $fulfilment                                            = Fulfilment::find($permission->scope_id);
                $authorisedFulfilments[$permission->scope_id]          = ['org_id' => $fulfilment->organisation_id];
                $authorisedOrganisations[$fulfilment->organisation_id] = ['org_id' => $fulfilment->organisation_id];
            } elseif ($permission->scope_type === 'Warehouse') {
                /** @var Warehouse $warehouse */
                $warehouse = Warehouse::find($permission->scope_id);
                if ($warehouse) {
                    $authorisedWarehouses[$permission->scope_id]          = ['org_id' => $warehouse->organisation_id];
                    $authorisedOrganisations[$warehouse->organisation_id] = ['org_id' => $warehouse->organisation_id];
                }
            } elseif ($permission->scope_type === 'Production') {
                /** @var Production $production */
                $production                                            = Production::find($permission->scope_id);
                $authorisedProductions[$permission->scope_id]          = ['org_id' => $production->organisation_id];
                $authorisedOrganisations[$production->organisation_id] = ['org_id' => $production->organisation_id];
            }
        }

        foreach ($user->group->organisations as $organisation) {
            if ($user->hasPermissionTo('accounting.'.$organisation->id.'.view')) {
                foreach ($organisation->shops as $shop) {
                    if ($shop->type === ShopTypeEnum::FULFILMENT) {
                        $authorisedFulfilments[$shop->fulfilment->id] = ['org_id' => $organisation->id];
                    } else {
                        $authorisedShops[$shop->id] = ['org_id' => $organisation->id];
                    }
                }
            }
        }


        $user->authorisedOrganisations()->sync($authorisedOrganisations);
        $user->authorisedShops()->sync($authorisedShops);
        $user->authorisedFulfilments()->sync($authorisedFulfilments);
        $user->authorisedWarehouses()->sync($authorisedWarehouses);
        $user->authorisedProductions()->sync($authorisedProductions);


        $stats = [
            'number_authorised_organisations' => count($authorisedOrganisations),
            'number_authorised_shops'         => count($authorisedShops),
            'number_authorised_fulfilments'   => count($authorisedFulfilments),
            'number_authorised_warehouses'    => count($authorisedWarehouses),
            'number_authorised_productions'   => count($authorisedProductions),
        ];

        $user->update($stats);

        foreach ($user->group->organisations as $organisation) {
            $user->revokePermissionTo('shops-view.'.$organisation->id);
            $user->revokePermissionTo('websites-view.'.$organisation->id);
        }

        $directPermissions = [];
        foreach ($authorisedShops as $shop) {
            $directPermissions['shops-view.'.$shop['org_id']]    = true;
            $directPermissions['websites-view.'.$shop['org_id']] = true;
        }

        $user->givePermissionTo(array_keys($directPermissions));
    }


    public string $commandSignature = 'user:set_authorised_models {user : User slug}';


    public function asCommand(Command $command): int
    {
        try {
            /** @var User $user */
            $user = User::where('slug', $command->argument('user'))->firstOrFail();
        } catch (Exception) {
            $command->error("User {$command->argument('user')} not found");

            return 1;
        }

        $this->handle($user);

        $command->info("User $user->contact_name authorised models set 🫡");

        return 0;
    }


}
