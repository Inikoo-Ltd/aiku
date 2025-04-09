<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\InvoiceCategory\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateInvoices;
use App\Actions\Traits\WithEnumStats;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceCategoryHydrateInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateInvoices;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(InvoiceCategory $invoiceCategory): string
    {
        return $invoiceCategory->id;
    }

    public function handle(InvoiceCategory $invoiceCategory): void
    {

        $stats = $this->getInvoicesStats($invoiceCategory);
        $invoiceCategory->stats()->update($stats);
    }



}
