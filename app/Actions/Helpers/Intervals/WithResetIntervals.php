<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Mar 2025 00:54:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSales;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRegistrationIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSales;
use App\Actions\Goods\Stock\Hydrators\StockHydrateSalesIntervals;
use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateSalesIntervals;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateSalesIntervals;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateSalesIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSales;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSales;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockFamily\OrgStockFamilyStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

trait WithResetIntervals
{
    use AsAction;

    protected array $intervals = [];
    protected array $doPreviousPeriods = [];

    protected function resetGroups(): void
    {
        foreach (Group::all() as $group) {
            GroupHydrateSales::dispatch(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods,
            );
            GroupHydrateInvoiceIntervals::dispatch(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
            GroupHydrateRegistrationIntervals::dispatch(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            GroupHydrateOrderIntervals::dispatch(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
            GroupHydrateOrderInBasketAtCreatedIntervals::dispatch(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            GroupHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }
    }

    protected function resetOrganisations(): void
    {
        foreach (Organisation::whereNot('type', OrganisationTypeEnum::AGENT)->get() as $organisation) {
            OrganisationHydrateSales::dispatch(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            OrganisationHydrateInvoiceIntervals::dispatch(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            OrganisationHydrateRegistrationIntervals::dispatch(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            OrganisationHydrateOrderIntervals::dispatch(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            OrganisationHydrateOrderInBasketAtCreatedIntervals::dispatch(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }
    }

    protected function resetShops(): void
    {
        foreach (
            Shop::whereIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN
            ])->get() as $shop
        ) {
            ShopHydrateSales::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            ShopHydrateInvoiceIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            ShopHydrateRegistrationIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            ShopHydrateOrderIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            ShopHydrateOrderInBasketAtCreatedIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            ShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }

        foreach (
            Shop::whereNotIn('state', [
                ShopStateEnum::OPEN,
                ShopStateEnum::CLOSING_DOWN
            ])->get() as $shop
        ) {
            ShopHydrateSales::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateInvoiceIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateRegistrationIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateOrderIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateOrderInBasketAtCreatedIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            ShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');
        }
    }

    protected function resetInvoiceCategories(): void
    {
        foreach (
            InvoiceCategory::whereIn('state', [
                InvoiceCategoryStateEnum::ACTIVE,
                InvoiceCategoryStateEnum::COOLDOWN
            ])->get() as $invoiceCategory
        ) {
            InvoiceCategoryHydrateSales::dispatch(
                invoiceCategory: $invoiceCategory,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            InvoiceCategoryHydrateOrderingIntervals::dispatch(
                invoiceCategory: $invoiceCategory,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');
        }

        foreach (
            InvoiceCategory::whereNotIn('state', [
                InvoiceCategoryStateEnum::ACTIVE,
                InvoiceCategoryStateEnum::COOLDOWN
            ])->get() as $invoiceCategory
        ) {
            InvoiceCategoryHydrateSales::run(
                invoiceCategory: $invoiceCategory,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            InvoiceCategoryHydrateOrderingIntervals::run(
                invoiceCategory: $invoiceCategory,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }
    }

    protected function resetStocks(): void
    {
        foreach (
            Stock::whereIn('state', [
                StockStateEnum::ACTIVE,
                StockStateEnum::DISCONTINUING
            ])->get() as $stock
        ) {
            StockHydrateSalesIntervals::dispatch(
                stock: $stock,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(15))->onQueue('sales');
        }

        foreach (
            Stock::whereNotIn('state', [
                StockStateEnum::ACTIVE,
                StockStateEnum::DISCONTINUING
            ])->get() as $stock
        ) {
            StockHydrateSalesIntervals::dispatch(
                stock: $stock,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(60))->onQueue('low-priority');
        }
    }

    protected function resetStockFamilies(): void
    {
        foreach (
            StockFamily::whereIn(
                'state',
                [
                    StockFamilyStateEnum::ACTIVE,
                    StockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $stockFamily
        ) {
            StockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $stockFamily,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(2))->onQueue('sales');
        }

        foreach (
            StockFamily::whereNotIn(
                'state',
                [
                    StockFamilyStateEnum::ACTIVE,
                    StockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $stockFamily
        ) {
            StockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $stockFamily,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(30))->onQueue('low-priority');
        }
    }

    protected function resetOrgStocks(): void
    {
        foreach (
            OrgStock::whereIn('state', [
                OrgStockStateEnum::ACTIVE,
                OrgStockStateEnum::DISCONTINUING
            ])->get() as $orgStock
        ) {
            OrgStockHydrateSalesIntervals::dispatch(
                stock: $orgStock,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(10))->onQueue('sales');
        }

        foreach (
            OrgStock::whereNotIn('state', [
                OrgStockStateEnum::ACTIVE,
                OrgStockStateEnum::DISCONTINUING
            ])->get() as $orgStock
        ) {
            OrgStockHydrateSalesIntervals::dispatch(
                stock: $orgStock,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(90))->onQueue('low-priority');
        }
    }

    protected function resetOrgStockFamilies(): void
    {
        foreach (
            OrgStockFamily::whereIn(
                'state',
                [
                    OrgStockFamilyStateEnum::ACTIVE,
                    OrgStockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $orgStockFamily
        ) {
            OrgStockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $orgStockFamily,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(2))->onQueue('sales');
        }

        foreach (
            OrgStockFamily::whereNotIn(
                'state',
                [
                    OrgStockFamilyStateEnum::ACTIVE,
                    OrgStockFamilyStateEnum::DISCONTINUING
                ]
            )->get() as $orgStockFamily
        ) {
            OrgStockFamilyHydrateSalesIntervals::dispatch(
                stockFamily: $orgStockFamily,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            )->delay(now()->addMinutes(45))->onQueue('low-priority');
        }
    }

    public function handle(): void
    {
        $this->resetGroups();
        $this->resetOrganisations();
        $this->resetShops();
        $this->resetInvoiceCategories();
        $this->resetStocks();
        $this->resetStockFamilies();
    }


    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }

}
