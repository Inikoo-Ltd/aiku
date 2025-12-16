<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 09 Feb 2022 15:04:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateAdjustments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateAssets;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateBrands;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeletedInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotesState;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamiliesWithNoDepartment;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrdersDispatchedToday;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateCreating;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateFinalised;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandling;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandlingBlocked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateInWarehouse;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStatePacked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateSubmitted;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOutboxes;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoFamily;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRegistrationIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRentals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateServices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateShippingCountries;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSubDepartments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCollections;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCreditTransactions;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCrmStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomerBalances;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomerInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomers;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDepartments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamilies;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePaymentAccounts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePayments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePlatformStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePolls;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProspects;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePurges;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTags;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopUps;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateVariants;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateVisitorsIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateWebUsers;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervals;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\Shop;

class HydrateShops
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:shops {organisations?*} {--s|slug=}';

    public function __construct()
    {
        $this->model = Shop::class;
    }

    public function handle(Shop $shop): void
    {
        ShopHydratePaymentAccounts::run($shop);
        ShopHydratePayments::run($shop);
        ShopHydrateCustomers::run($shop);
        ShopHydrateCustomerInvoices::run($shop);
        ShopHydrateOrders::run($shop);
        ShopHydratePurges::run($shop);
        ShopHydrateDeliveryNotes::run($shop->id, DeliveryNoteTypeEnum::ORDER);
        ShopHydrateDeliveryNotes::run($shop->id, DeliveryNoteTypeEnum::REPLACEMENT);
        ShopHydrateDepartments::run($shop);
        ShopHydrateFamilies::run($shop);
        ShopHydrateInvoices::run($shop);
        ShopHydrateSalesIntervals::run($shop);
        ShopHydrateProducts::run($shop);
        ShopHydrateCollections::run($shop);
        ShopHydrateAssets::run($shop);
        ShopHydrateVariants::run($shop);
        ShopHydrateServices::run($shop);
        ShopHydrateSubDepartments::run($shop);
        ShopHydrateOutboxes::run($shop);
        ShopHydrateTopUps::run($shop);
        ShopHydrateCreditTransactions::run($shop);
        ShopHydrateCustomerBalances::run($shop);
        ShopHydrateInvoiceIntervals::run($shop);
        ShopHydrateRentals::run($shop);
        ShopHydrateCrmStats::run($shop);
        ShopHydrateAdjustments::run($shop);

        ShopHydrateOrderStateCreating::run($shop->id);
        ShopHydrateOrderStateSubmitted::run($shop->id);
        ShopHydrateOrderStateInWarehouse::run($shop->id);
        ShopHydrateOrderStateHandling::run($shop->id);
        ShopHydrateOrderStateHandlingBlocked::run($shop->id);
        ShopHydrateOrderStatePacked::run($shop->id);
        ShopHydrateOrderStateFinalised::run($shop->id);
        ShopHydrateOrdersDispatchedToday::run($shop->id);


        ShopHydrateDeletedInvoices::run($shop);
        ShopHydrateOrderIntervals::run($shop);
        ShopHydrateRegistrationIntervals::run($shop->id);
        ShopHydrateOrderIntervals::run($shop);
        ShopHydrateMailshots::run($shop);
        ShopHydrateOrderInBasketAtCreatedIntervals::run($shop);
        ShopHydrateOrderInBasketAtCustomerUpdateIntervals::run($shop);
        ShopHydrateFamiliesWithNoDepartment::run($shop);
        ShopHydrateProductsWithNoFamily::run($shop);
        ShopHydratePolls::run($shop);
        ShopHydrateWebUsers::run($shop);
        ShopHydrateVisitorsIntervals::run($shop);
        ShopHydratePlatformStats::run($shop);
        ShopHydrateProspects::run($shop);
        ShopHydrateTags::run($shop);
        ShopHydrateBrands::run($shop);
        ShopHydrateShippingCountries::run($shop);

        if ($shop->type == ShopTypeEnum::DROPSHIPPING) {
            ShopHydratePlatformSalesIntervals::run($shop);
        }

        foreach (DeliveryNoteStateEnum::cases() as $case) {
            ShopHydrateDeliveryNotesState::run($shop->id, $case);
        }

    }

}
