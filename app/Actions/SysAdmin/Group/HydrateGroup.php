<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group;

use App\Actions\HydrateModel;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAdjustments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAgents;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateApiTokens;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateAudits;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateBanners;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCharges;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateClockingMachines;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCollections;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCreditTransactions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomerBalances;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCustomers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeliveryNotes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDispatchedEmails;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateEmailAddresses;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateEmailsBulkRuns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateEmployees;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateFulfilmentCustomers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateGuests;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterShops;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOfferCampaigns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgPostRooms;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOutboxes;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletDeliveries;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePalletReturns;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePallets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePostRooms;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProductsWithNoFamily;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShops;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStockFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStocks;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceTransactions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateJobPositions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateLocations;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderHandling;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrganisations;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgStockFamilies;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgStockMovements;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgStocks;
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
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateProspects;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurchaseOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePurges;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRedirects;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateServices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSpaces;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStockDeliveries;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStoredItemAudits;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStoredItems;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSubDepartments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSubscriptions;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSupplierProducts;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSysadminIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTimesheets;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTopUps;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnits;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUserRequests;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUsers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateVariants;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouseAreas;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWarehouses;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebpages;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebsites;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebUsers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateDeletedInvoices;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\SysAdmin\Group;

class HydrateGroup extends HydrateModel
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:groups';

    public function __construct()
    {
        $this->model = Group::class;
    }

    public function handle(Group $group): void
    {
        GroupHydrateAudits::run($group);
        GroupHydrateGuests::run($group);
        GroupHydrateJobPositions::run($group);
        GroupHydrateOrganisations::run($group);
        GroupHydrateAgents::run($group);
        GroupHydrateSuppliers::run($group);
        GroupHydrateProductSuppliers::run($group);
        GroupHydrateStocks::run($group);
        GroupHydrateStockFamilies::run($group);
        GroupHydrateStockDeliveries::run($group);
        GroupHydrateTradeUnits::run($group);
        GroupHydrateUsers::run($group);
        GroupHydrateInvoices::run($group);
        GroupHydratePayments::run($group);
        GroupHydratePaymentAccounts::run($group);
        GroupHydratePaymentServiceProviders::run($group);
        GroupHydrateSales::run($group);
        GroupHydrateOrderIntervals::run($group);
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
        GroupHydrateCharges::run($group);
        GroupHydrateCustomers::run($group);
        GroupHydrateProspects::run($group);
        GroupHydrateOrgStocks::run($group);
        GroupHydrateOrgStockFamilies::run($group);
        GroupHydrateOrgStockMovements::run($group);
        GroupHydrateTimesheets::run($group);
        GroupHydrateSubscriptions::run($group);
        GroupHydratePurchaseOrders::run($group);
        GroupHydrateInvoiceIntervals::run($group);

        GroupHydrateMasterShops::run($group);

        GroupHydrateShops::run($group);
        GroupHydrateBanners::run($group);
        GroupHydrateWebsites::run($group);
        GroupHydrateWebpages::run($group);
        GroupHydrateRedirects::run($group);
        GroupHydrateDepartments::run($group);
        GroupHydrateSubDepartments::run($group);
        GroupHydrateFamilies::run($group);
        GroupHydratePostRooms::run($group);
        GroupHydrateOrgPostRooms::run($group);
        GroupHydrateOutboxes::run($group);
        GroupHydrateDispatchedEmails::run($group);
        GroupHydrateEmailsBulkRuns::run($group);
        GroupHydrateSupplierProducts::run($group);
        GroupHydrateMailshots::run($group);
        GroupHydrateEmailAddresses::run($group);
        GroupHydrateUserRequests::run($group);
        GroupHydrateCustomerBalances::run($group);
        GroupHydrateSysadminIntervals::run($group);
        GroupHydrateInvoiceTransactions::run($group);
        GroupHydrateVariants::run($group);

        GroupHydrateOrderHandling::run($group);
        //fulfilment
        GroupHydratePallets::run($group);
        GroupHydratePalletDeliveries::run($group);
        GroupHydratePalletReturns::run($group);
        GroupHydrateStoredItemAudits::run($group);
        GroupHydrateStoredItems::run($group);
        GroupHydrateRecurringBills::run($group);
        GroupHydrateSpaces::run($group);
        GroupHydrateFulfilmentCustomers::run($group);

        GroupHydrateTopUps::run($group);
        GroupHydrateCreditTransactions::run($group);

        GroupHydrateOfferCampaigns::run($group);
        GroupHydrateOffers::run($group);

        GroupHydrateOrders::run($group);
        GroupHydratePurges::run($group);
        GroupHydrateDeliveryNotes::run($group);
        GroupHydrateAdjustments::run($group);
        GroupHydrateWebUsers::run($group);
        GroupHydrateDeletedInvoices::run($group);
        GroupHydrateRegistrationIntervals::run($group);

        GroupHydrateOrderInBasketAtCustomerUpdateIntervals::run($group);
        GroupHydrateOrderInBasketAtCreatedIntervals::run($group);
        GroupHydrateApiTokens::run($group);

        GroupHydrateFamiliesWithNoDepartment::run($group);
        GroupHydrateProductsWithNoFamily::run($group);


    }

}
