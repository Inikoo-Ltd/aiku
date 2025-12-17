<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Actions\HydrateModel;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateAdjustments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateAudits;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCollections;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCreditTransactions;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCustomerBalances;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeletedInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDepartments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateDispatchedEmails;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamilies;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateFamiliesWithNoDepartment;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceCategories;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateLocations;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateCreating;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandling;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStatePacked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateSubmitted;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgPostRooms;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSuppliers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOutboxes;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletDeliveries;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePalletReturns;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePallets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProductsWithNoFamily;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRawMaterials;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePaymentAccounts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePayments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCustomers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateEmployees;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateJobPositions;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShops;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgPaymentServiceProviders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProductions;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurchaseOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateProspects;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRecurringBills;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRentals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateServices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePurges;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRedirects;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShopTypeDeliveryNotesState;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSpaces;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateStoredItemAudits;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateStoredItems;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSubDepartments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSubscription;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateTopUps;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateVariants;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWarehouseAreas;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWarehouses;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebpages;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebsites;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebUserRequests;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebUsers;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;

class HydrateOrganisations extends HydrateModel
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:organisations {--s|slug=}';

    public function __construct()
    {
        $this->model = Organisation::class;
    }

    public function handle(Organisation $organisation): void
    {
        OrganisationHydrateAudits::run($organisation);
        OrganisationHydrateEmployees::run($organisation);
        OrganisationHydrateShops::run($organisation);
        OrganisationHydratePayments::run($organisation);
        OrganisationHydratePaymentAccounts::run($organisation);
        OrganisationHydrateOrgPaymentServiceProviders::run($organisation);
        OrganisationHydrateCustomers::run($organisation);
        OrganisationHydrateOrders::run($organisation);
        OrganisationHydratePurges::run($organisation);
        OrganisationHydrateDeliveryNotes::run($organisation->id, DeliveryNoteTypeEnum::ORDER);
        OrganisationHydrateDeliveryNotes::run($organisation->id, DeliveryNoteTypeEnum::REPLACEMENT);
        ;

        OrganisationHydratePurchaseOrders::run($organisation);
        OrganisationHydrateWebsites::run($organisation);
        OrganisationHydrateWebpages::run($organisation);
        OrganisationHydrateRedirects::run($organisation);
        OrganisationHydrateProspects::run($organisation);
        OrganisationHydrateJobPositions::run($organisation);
        OrganisationHydrateOrgStocks::run($organisation);

        OrganisationHydrateInvoices::run($organisation);
        OrganisationHydrateInvoiceCategories::run($organisation);
        OrganisationHydrateOrderIntervals::run($organisation);
        OrganisationHydrateSalesIntervals::run($organisation);
        OrganisationHydrateSubscription::run($organisation);
        OrganisationHydrateServices::run($organisation);
        OrganisationHydrateOrgPostRooms::run($organisation);
        OrganisationHydrateOutboxes::run($organisation);
        OrganisationHydrateCustomerBalances::run($organisation);
        OrganisationHydrateDispatchedEmails::run($organisation);
        OrganisationHydrateWebUsers::run($organisation);



        if ($organisation->type == OrganisationTypeEnum::SHOP) {
            OrganisationHydrateRegistrationIntervals::run($organisation->id);
            OrganisationHydrateInvoiceIntervals::run($organisation);

            OrganisationHydrateDepartments::run($organisation);
            OrganisationHydrateSubDepartments::run($organisation);
            OrganisationHydrateFamilies::run($organisation);
            OrganisationHydrateCollections::run($organisation);
            OrganisationHydrateProductions::run($organisation);
            OrganisationHydrateWarehouses::run($organisation);
            OrganisationHydrateWarehouseAreas::run($organisation);
            OrganisationHydrateLocations::run($organisation);
            OrganisationHydrateRawMaterials::run($organisation);
            OrganisationHydrateProducts::run($organisation);
            OrganisationHydrateRentals::run($organisation);
            OrganisationHydrateVariants::run($organisation);

            OrganisationHydrateOrgAgents::run($organisation);
            OrganisationHydrateOrgSuppliers::run($organisation);
            OrganisationHydrateOrgSupplierProducts::run($organisation);

            //fulfilment
            OrganisationHydratePallets::run($organisation);
            OrganisationHydratePalletDeliveries::run($organisation);
            OrganisationHydratePalletReturns::run($organisation);
            OrganisationHydrateStoredItemAudits::run($organisation);
            OrganisationHydrateStoredItems::run($organisation);
            OrganisationHydrateRecurringBills::run($organisation);
            OrganisationHydrateSpaces::run($organisation);

            OrganisationHydrateTopUps::run($organisation);
            OrganisationHydrateCreditTransactions::run($organisation);
            OrganisationHydrateAdjustments::run($organisation);

            OrganisationHydrateOrderStateCreating::run($organisation->id);
            OrganisationHydrateOrderStateSubmitted::run($organisation->id);
            OrganisationHydrateOrderStateInWarehouse::run($organisation->id);
            OrganisationHydrateOrderStateHandling::run($organisation->id);
            OrganisationHydrateOrderStateHandlingBlocked::run($organisation->id);
            OrganisationHydrateOrderStatePacked::run($organisation->id);
            OrganisationHydrateOrderStateFinalised::run($organisation->id);
            OrganisationHydrateOrdersDispatchedToday::run($organisation->id);

            OrganisationHydrateMailshots::run($organisation);
            OrganisationHydrateDeletedInvoices::run($organisation);

            OrganisationHydrateOrderInBasketAtCreatedIntervals::run($organisation);
            OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals::run($organisation);

            OrganisationHydrateFamiliesWithNoDepartment::run($organisation);
            OrganisationHydrateProductsWithNoFamily::run($organisation);
            OrganisationHydrateWebUserRequests::run($organisation->id);

            foreach (DeliveryNoteStateEnum::cases() as $case) {
                OrganisationHydrateDeliveryNotesState::run($organisation->id, $case);
            }

            foreach (ShopTypeEnum::cases() as $type) {
                if ($type != ShopTypeEnum::FULFILMENT) {
                    foreach (DeliveryNoteStateEnum::cases() as $deliveryNoteState) {
                        OrganisationHydrateShopTypeDeliveryNotesState::run($organisation->id, $type, $deliveryNoteState);
                    }
                }
            }
        }
    }


}
