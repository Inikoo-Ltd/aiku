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
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSales;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSales;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSales;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
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
            GroupHydrateSales::run(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods,
            );
            GroupHydrateInvoiceIntervals::run(
                group: $group,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }
    }

    protected function resetOrganisations(): void
    {
        foreach (Organisation::whereNot('type', OrganisationTypeEnum::AGENT)->get() as $organisation) {
            OrganisationHydrateSales::run(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            OrganisationHydrateInvoiceIntervals::run(
                organisation: $organisation,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }
    }

    protected function resetShops(): void
    {
        foreach (Shop::all() as $shop) {
            ShopHydrateSales::run(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );

            ShopHydrateInvoiceIntervals::run(
                shop: $shop,
                intervals: $this->intervals,
                doPreviousPeriods: $this->doPreviousPeriods
            );
        }
    }

    protected function resetInvoiceCategories(): void
    {
        foreach (InvoiceCategory::all() as $invoiceCategory) {
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

    public function handle(): void
    {
        $this->resetGroups();
        $this->resetOrganisations();
        $this->resetShops();
        $this->resetInvoiceCategories();
    }


    public function asCommand(): int
    {
        $this->handle();
        return 0;
    }

}
