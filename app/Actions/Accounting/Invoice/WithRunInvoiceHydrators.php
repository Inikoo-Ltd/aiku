<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Mar 2025 19:27:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Search\InvoiceRecordSearch;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Billables\ShippingZone\Hydrators\ShippingZoneHydrateUsageInInvoices;
use App\Actions\Billables\ShippingZoneSchema\Hydrators\ShippingZoneSchemaHydrateUsageInInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSalesIntervals;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsInvoices;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsSales;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsSalesGrpCurrency;
use App\Actions\Dropshipping\Platform\Shop\Hydrators\ShopHydratePlatformSalesIntervalsSalesOrgCurrency;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateInvoiceIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateSalesIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSalesIntervals;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\GroupSalesIntervals;

trait WithRunInvoiceHydrators
{
    public function runInvoiceHydrators(Invoice $invoice): void
    {
        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);
        OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
        GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);


        if ($invoice->invoiceCategory) {
            InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateSalesIntervals::dispatch($invoice->invoiceCategory, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        }

        ShopHydrateSalesIntervals::dispatch($invoice->shop, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        OrganisationHydrateSalesIntervals::dispatch($invoice->organisation, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        GroupHydrateSalesIntervals::dispatch($invoice->group, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);

        if ($invoice->master_shop_id) {
            MasterShopHydrateSalesIntervals::dispatch($invoice->master_shop_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            MasterShopHydrateInvoiceIntervals::dispatch($invoice->master_shop_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        }

        ShopHydrateInvoiceIntervals::dispatch($invoice->shop, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        OrganisationHydrateInvoiceIntervals::dispatch($invoice->organisation, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        GroupHydrateInvoiceIntervals::dispatch($invoice->group, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);

        if ($invoice->shipping_zone_id) {
            ShippingZoneHydrateUsageInInvoices::dispatch($invoice->shipping_zone_id)->delay($this->hydratorsDelay);
        }
        if ($invoice->shipping_zone_schema_id) {
            ShippingZoneSchemaHydrateUsageInInvoices::dispatch($invoice->shipping_zone_schema_id)->delay($this->hydratorsDelay);
        }

        CustomerHydrateClv::dispatch($invoice->customer)->delay($this->hydratorsDelay);

        InvoiceRecordSearch::dispatch($invoice);

        if ($invoice->platform_id) {
            ShopHydratePlatformSalesIntervalsInvoices::dispatch($invoice->shop_id, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            ShopHydratePlatformSalesIntervalsSales::dispatch($invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            ShopHydratePlatformSalesIntervalsSalesOrgCurrency::dispatch($invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            ShopHydratePlatformSalesIntervalsSalesGrpCurrency::dispatch($invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
        }
    }
}
