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
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSales;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoiceIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateSales;
use App\Actions\Comms\Email\SendInvoiceEmailToCustomer;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSales;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateSales;
use App\Models\Accounting\Invoice;

trait WithRunInvoiceHydrators
{
    public function runInvoiceHydrators(Invoice $invoice): void
    {


        ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);
        OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
        GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);


        if ($invoice->invoiceCategory) {
            InvoiceCategoryHydrateInvoices::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateSales::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
            InvoiceCategoryHydrateOrderingIntervals::dispatch($invoice->invoiceCategory)->delay($this->hydratorsDelay);
        }

        ShopHydrateSales::dispatch($invoice->shop)->delay($this->hydratorsDelay);
        OrganisationHydrateSales::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
        GroupHydrateSales::dispatch($invoice->group)->delay($this->hydratorsDelay);

        ShopHydrateInvoiceIntervals::dispatch($invoice->shop)->delay($this->hydratorsDelay);
        OrganisationHydrateInvoiceIntervals::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
        GroupHydrateInvoiceIntervals::dispatch($invoice->group)->delay($this->hydratorsDelay);

        InvoiceRecordSearch::dispatch($invoice);

        if ($this->strict) {
            SendInvoiceEmailToCustomer::dispatch($invoice);
        }
    }
}
