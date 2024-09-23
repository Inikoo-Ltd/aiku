<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\HydrateModel;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAgents;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateClockingMachines;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCollectionCategories;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCollections;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCreditTransactions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateEmployees;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateGuests;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOfferCampaigns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOutboxes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePallets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePostRooms;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShops;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStockFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStocks;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateJobPositions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateLocations;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrganisations;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePaymentAccounts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePayments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePaymentServiceProviders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRawMaterials;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRecurringBills;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRentals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSales;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateServices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStoredItemAudits;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStoredItems;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSubDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSubscriptions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTopUps;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnits;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUsers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouseAreas;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouses;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebpages;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebsites;
use App\Actions\Traits\WithNormalise;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Console\Command;

class HydrateGroup extends HydrateModel
{
    use WithNormalise;

    public string $commandSignature = 'hydrate:groups {group? : Group slug}';


    public function handle(Group $group): void
    {
        GroupHydrateGuests::run($group);
        GroupHydrateJobPositions::run($group);
        GroupHydrateOrganisations::run($group);
        GroupHydrateAgents::run($group);
        GroupHydrateSuppliers::run($group);
        GroupHydrateProductSuppliers::run($group);
        GroupHydrateStocks::run($group);
        GroupHydrateStockFamilies::run($group);
        GroupHydrateTradeUnits::run($group);
        GroupHydrateUsers::run($group);
        GroupHydrateInvoices::run($group);
        GroupHydratePayments::run($group);
        GroupHydratePaymentAccounts::run($group);
        GroupHydratePaymentServiceProviders::run($group);
        GroupHydrateSales::run($group);
        GroupHydrateCollectionCategories::run($group);
        GroupHydrateCollections::run($group);
        GroupHydrateWarehouses::run($group);
        GroupHydrateWarehouseAreas::run($group);
        GroupHydrateLocations::run($group);
        GroupHydrateProductions::run($group);
        GroupHydrateRawMaterials::run($group);
        GroupHydrateEmployees::run($group);
        GroupHydrateClockingMachines::run($group);
        GroupHydrateProducts::run($group);
        GroupHydrateRentals::run($group);
        GroupHydrateServices::run($group);
        GroupHydrateSubscriptions::run($group);
        GroupHydrateShops::run($group);
        GroupHydrateWebsites::run($group);
        GroupHydrateWebpages::run($group);
        GroupHydrateDepartments::run($group);
        GroupHydrateSubDepartments::run($group);
        GroupHydrateFamilies::run($group);
        GroupHydratePostRooms::run($group);
        GroupHydrateOutboxes::run($group);
        GroupHydrateSupplierProducts::run($group);

        //fulfilment
        GroupHydratePallets::run($group);
        GroupHydratePalletDeliveries::run($group);
        GroupHydratePalletReturns::run($group);
        GroupHydrateStoredItemAudits::run($group);
        GroupHydrateStoredItems::run($group);
        GroupHydrateRecurringBills::run($group);

        GroupHydrateTopUps::run($group);
        GroupHydrateCreditTransactions::run($group);

        GroupHydrateOfferCampaigns::run($group);
        GroupHydrateOffers::run($group);

        GroupHydrateOrders::run($group);
        GroupHydrateDeliveryNotes::run($group);


    }


    public function asCommand(Command $command): int
    {
        if ($command->argument('group')) {
            try {
                $group = Group::where('slug', $command->argument('group'))->firstorFail();
            } catch (Exception $e) {
                $command->error($e->getMessage());

                return 1;
            }


            $this->handle($group);
        } else {
            $groups = Group::all();

            foreach ($groups as $group) {
                $this->handle($group);
            }
        }

        return 0;
    }
}
