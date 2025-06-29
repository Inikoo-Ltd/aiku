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
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeletedInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateDeliveryNotes;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateFamiliesWithNoDepartment;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOutboxes;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoFamily;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRegistrationIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRentals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateServices;
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
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderHandling;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePaymentAccounts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePayments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePolls;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProducts;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePurges;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSales;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopUps;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateVariants;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateWebUsers;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
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
        ShopHydrateDeliveryNotes::run($shop);
        ShopHydrateDepartments::run($shop);
        ShopHydrateFamilies::run($shop);
        ShopHydrateInvoices::run($shop);
        ShopHydrateSales::run($shop);
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
        ShopHydrateOrderHandling::run($shop);
        ShopHydrateDeletedInvoices::run($shop);
        ShopHydrateOrderIntervals::run($shop);
        ShopHydrateRegistrationIntervals::run($shop);
        ShopHydrateOrderIntervals::run($shop);
        ShopHydrateMailshots::run($shop);
        ShopHydrateOrderInBasketAtCreatedIntervals::run($shop);
        ShopHydrateOrderInBasketAtCustomerUpdateIntervals::run($shop);
        ShopHydrateFamiliesWithNoDepartment::run($shop);
        ShopHydrateProductsWithNoFamily::run($shop);
        ShopHydratePolls::run($shop);
        ShopHydrateWebUsers::run($shop);
    }

}
