<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 15:13:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\Hydrators\InvoiceHydrateOffers;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Accounting\Invoice;

class HydrateInvoices
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:invoices {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = Invoice::class;
    }

    public function handle(Invoice $invoice): void
    {
        InvoiceHydrateOffers::run($invoice);
        UpdateInvoicePaymentState::run($invoice);
    }



}
