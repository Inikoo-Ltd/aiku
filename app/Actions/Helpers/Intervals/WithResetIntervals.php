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
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRegistrationIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSales;
use App\Actions\Goods\Stock\Hydrators\StockHydrateSalesIntervals;
use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateSalesIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSales;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSales;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
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
