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
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RunInvoiceHydrators
{

    use asAction;

    public function handle(Invoice $invoice, int $hydratorsDelay = 0): void
    {
        // Todo: remove (testing in dashboard)
        $hydratorsDelay = 0;

        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        ShopHydrateInvoices::dispatch($invoice->shop)->delay($hydratorsDelay);
        OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($hydratorsDelay);
        GroupHydrateInvoices::dispatch($invoice->group)->delay($hydratorsDelay);


        if ($invoice->invoiceCategory) {
            InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($hydratorsDelay);
            InvoiceCategoryHydrateSalesIntervals::dispatch($invoice->invoiceCategory, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
            InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        }

        ShopHydrateSalesIntervals::dispatch($invoice->shop, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        OrganisationHydrateSalesIntervals::dispatch($invoice->organisation, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        GroupHydrateSalesIntervals::dispatch($invoice->group, $intervalsExceptHistorical, [])->delay($hydratorsDelay);

        if ($invoice->master_shop_id) {
            MasterShopHydrateSalesIntervals::dispatch($invoice->master_shop_id, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
            MasterShopHydrateInvoiceIntervals::dispatch($invoice->master_shop_id, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        }

        ShopHydrateInvoiceIntervals::dispatch($invoice->shop, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        OrganisationHydrateInvoiceIntervals::dispatch($invoice->organisation, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        GroupHydrateInvoiceIntervals::dispatch($invoice->group, $intervalsExceptHistorical, [])->delay($hydratorsDelay);

        if ($invoice->shipping_zone_id) {
            ShippingZoneHydrateUsageInInvoices::dispatch($invoice->shipping_zone_id)->delay($hydratorsDelay);
        }
        if ($invoice->shipping_zone_schema_id) {
            ShippingZoneSchemaHydrateUsageInInvoices::dispatch($invoice->shipping_zone_schema_id)->delay($hydratorsDelay);
        }

        CustomerHydrateClv::dispatch($invoice->customer_id)->delay($hydratorsDelay);

        InvoiceRecordSearch::dispatch($invoice);

        if ($invoice->platform_id) {
            ShopHydratePlatformSalesIntervalsInvoices::dispatch($invoice->shop_id, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
            ShopHydratePlatformSalesIntervalsSales::dispatch($invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
            ShopHydratePlatformSalesIntervalsSalesOrgCurrency::dispatch($invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
            ShopHydratePlatformSalesIntervalsSalesGrpCurrency::dispatch($invoice->shop, $invoice->platform_id, $intervalsExceptHistorical, [])->delay($hydratorsDelay);
        }
    }

    public function getCommandSignature(): string
    {
        return 'accounting:invoice:run-hydrators {invoice}';
    }

    public function asCommand(Command $command): int
    {
        $invoice = Invoice::where('slug', $command->argument('invoice'))->firstOrFail();
        $this->handle($invoice);

        return 0;
    }
}
