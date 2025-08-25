<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 15:13:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateInvoices;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Accounting\InvoiceCategory;

class HydrateInvoiceCategories
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:invoice_categories {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = InvoiceCategory::class;
    }

    public function handle(InvoiceCategory $invoiceCategory): void
    {
        InvoiceCategoryHydrateInvoices::run($invoiceCategory);
        InvoiceCategoryHydrateOrderingIntervals::run($invoiceCategory);
        InvoiceCategoryHydrateSalesIntervals::run($invoiceCategory);
    }



}
